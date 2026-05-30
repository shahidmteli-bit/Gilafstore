<?php
/**
 * Lightweight WebAuthn (Passkey) Implementation
 * Supports ES256 (P-256) + RS256 credentials
 * No external dependencies — uses PHP's built-in openssl extension
 * Compliant with WebAuthn Level 2 core flows
 */

class SimpleWebAuthn
{
    private string $rpId;
    private string $rpName;
    private string $origin;

    public function __construct(string $rpId = '', string $rpName = 'Gilaf Store')
    {
        if ($rpId === '') {
            $rpId = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $rpId = preg_replace('/:\d+$/', '', $rpId);
        }
        $this->rpId = $rpId;
        $this->rpName = $rpName;

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $this->origin = $scheme . '://' . $this->rpId;
    }

    /* ───────────────── Registration ───────────────── */

    public function getRegistrationOptions(int $userId, string $userName, string $displayName, array $excludeIds = [], string $attachment = ''): array
    {
        $challenge = random_bytes(32);

        $authSelection = [
            'residentKey'      => 'preferred',
            'userVerification' => 'preferred',
        ];
        // Force cross-platform (phone/tablet) or platform (this device) if specified
        if ($attachment === 'cross-platform' || $attachment === 'platform') {
            $authSelection['authenticatorAttachment'] = $attachment;
        }

        $options = [
            'challenge' => self::b64url($challenge),
            'rp' => ['id' => $this->rpId, 'name' => $this->rpName],
            'user' => [
                'id'          => self::b64url(pack('N', $userId)),
                'name'        => $userName,
                'displayName' => $displayName,
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],    // ES256
                ['type' => 'public-key', 'alg' => -257],  // RS256
            ],
            'authenticatorSelection' => $authSelection,
            'timeout'     => 120000,
            'attestation' => 'none',
        ];

        if ($excludeIds) {
            $options['excludeCredentials'] = array_map(
                fn($id) => ['type' => 'public-key', 'id' => $id],
                $excludeIds
            );
        }

        return ['options' => $options, 'challenge' => self::b64url($challenge)];
    }

    public function verifyRegistration(string $clientDataB64, string $attestationB64, string $expectedChallenge): array|false
    {
        // 1. clientDataJSON
        $cdJSON = self::b64urlDec($clientDataB64);
        $cd = json_decode($cdJSON, true);
        if (!$cd) return false;
        if (($cd['type'] ?? '') !== 'webauthn.create') return false;
        if (($cd['challenge'] ?? '') !== $expectedChallenge) return false;
        if (!$this->originOk($cd['origin'] ?? '')) return false;

        // 2. attestationObject (CBOR)
        $att = CBORLite::decode(self::b64urlDec($attestationB64));
        if (!is_array($att) || !isset($att['authData'])) return false;

        $authData = $att['authData'];
        if ($authData instanceof CBORBytes) $authData = $authData->v;

        // 3. Parse authData
        $p = $this->parseAuthData($authData, true);
        if (!$p) return false;

        // 4. rpIdHash
        if (!hash_equals(hash('sha256', $this->rpId, true), $p['rpIdHash'])) return false;

        // 5. User-present flag
        if (!($p['flags'] & 0x01)) return false;

        // 6. Credential
        if (empty($p['credId']) || empty($p['coseKey'])) return false;

        // 7. COSE → PEM
        $pem = $this->coseToPem($p['coseKey']);
        if (!$pem) return false;

        return [
            'credentialId' => self::b64url($p['credId']),
            'publicKeyPem' => $pem,
            'signCount'    => $p['signCount'],
        ];
    }

    /* ───────────────── Authentication ───────────────── */

    public function getAuthenticationOptions(array $credentialIds): array
    {
        $challenge = random_bytes(32);

        $allow = array_map(fn($id) => [
            'type' => 'public-key',
            'id'   => $id,
        ], $credentialIds);

        return [
            'options' => [
                'challenge'        => self::b64url($challenge),
                'rpId'             => $this->rpId,
                'allowCredentials' => $allow,
                'timeout'          => 120000,
                'userVerification' => 'preferred',
            ],
            'challenge' => self::b64url($challenge),
        ];
    }

    public function verifyAuthentication(
        string $credIdB64,
        string $clientDataB64,
        string $authDataB64,
        string $signatureB64,
        string $expectedChallenge,
        string $publicKeyPem,
        int    $storedSignCount
    ): array|false {
        // 1. clientDataJSON
        $cdJSON = self::b64urlDec($clientDataB64);
        $cd = json_decode($cdJSON, true);
        if (!$cd) return false;
        if (($cd['type'] ?? '') !== 'webauthn.get') return false;
        if (($cd['challenge'] ?? '') !== $expectedChallenge) return false;
        if (!$this->originOk($cd['origin'] ?? '')) return false;

        // 2. authenticatorData
        $authData = self::b64urlDec($authDataB64);
        $p = $this->parseAuthData($authData, false);
        if (!$p) return false;

        // 3. rpIdHash
        if (!hash_equals(hash('sha256', $this->rpId, true), $p['rpIdHash'])) return false;

        // 4. User-present
        if (!($p['flags'] & 0x01)) return false;

        // 5. Signature verification
        $clientDataHash = hash('sha256', $cdJSON, true);
        $signedData     = $authData . $clientDataHash;
        $sig            = self::b64urlDec($signatureB64);

        $pk = openssl_pkey_get_public($publicKeyPem);
        if (!$pk) return false;
        if (openssl_verify($signedData, $sig, $pk, OPENSSL_ALGO_SHA256) !== 1) return false;

        // 6. Sign-count replay check
        if ($storedSignCount > 0 && $p['signCount'] > 0 && $p['signCount'] <= $storedSignCount) {
            return false;
        }

        return ['newSignCount' => $p['signCount']];
    }

    /* ───────────────── authData parser ───────────────── */

    private function parseAuthData(string $d, bool $wantCred): array|false
    {
        if (strlen($d) < 37) return false;

        $r = [
            'rpIdHash'  => substr($d, 0, 32),
            'flags'     => ord($d[32]),
            'signCount' => unpack('N', substr($d, 33, 4))[1],
            'credId'    => '',
            'coseKey'   => null,
        ];

        if ($wantCred && ($r['flags'] & 0x40)) {
            if (strlen($d) < 55) return false;
            $credLen = unpack('n', substr($d, 53, 2))[1];
            if (strlen($d) < 55 + $credLen) return false;

            $r['credId'] = substr($d, 55, $credLen);
            $r['coseKey'] = CBORLite::decode(substr($d, 55 + $credLen));
        }

        return $r;
    }

    /* ───────────────── COSE → PEM ───────────────── */

    private function coseToPem(array $k): string|false
    {
        $kty = $k[1] ?? null;
        $alg = $k[3] ?? null;

        // ES256 — EC P-256
        if ($kty === 2 && $alg === -7) {
            $x = $k[-2] ?? '';
            $y = $k[-3] ?? '';
            if ($x instanceof CBORBytes) $x = $x->v;
            if ($y instanceof CBORBytes) $y = $y->v;
            if (strlen($x) !== 32 || strlen($y) !== 32) return false;

            // SubjectPublicKeyInfo for EC P-256 uncompressed point
            $header = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
            return self::derToPem($header . "\x04" . $x . $y);
        }

        // RS256 — RSA
        if ($kty === 3 && $alg === -257) {
            $n = $k[-1] ?? '';
            $e = $k[-2] ?? '';
            if ($n instanceof CBORBytes) $n = $n->v;
            if ($e instanceof CBORBytes) $e = $e->v;
            if ($n === '' || $e === '') return false;

            $rsaSeq = self::asn1Seq(self::asn1Int($n) . self::asn1Int($e));
            $bits   = "\x03" . self::asn1Len(strlen($rsaSeq) + 1) . "\x00" . $rsaSeq;
            $algId  = self::asn1Seq(hex2bin('06092a864886f70d0101010500'));
            return self::derToPem(self::asn1Seq($algId . $bits));
        }

        return false;
    }

    /* ───────────────── ASN.1 helpers ───────────────── */

    private static function asn1Int(string $d): string
    {
        if (ord($d[0]) & 0x80) $d = "\x00" . $d;
        return "\x02" . self::asn1Len(strlen($d)) . $d;
    }

    private static function asn1Seq(string $d): string
    {
        return "\x30" . self::asn1Len(strlen($d)) . $d;
    }

    private static function asn1Len(int $n): string
    {
        if ($n < 128) return chr($n);
        if ($n < 256) return "\x81" . chr($n);
        return "\x82" . pack('n', $n);
    }

    private static function derToPem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    /* ───────────────── Origin check ───────────────── */

    private function originOk(string $o): bool
    {
        if (preg_match('/^https?:\/\/localhost(:\d+)?$/', $o)) return true;
        return $o === $this->origin || $o === 'https://' . $this->rpId;
    }

    /* ───────────────── Base64url ───────────────── */

    public static function b64url(string $d): string
    {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }

    public static function b64urlDec(string $d): string
    {
        return base64_decode(strtr($d, '-_', '+/') . str_repeat('=', (4 - strlen($d) % 4) % 4));
    }
}

/* ═══════════════ Minimal CBOR Decoder ═══════════════ */

class CBORBytes
{
    public string $v;
    public function __construct(string $v) { $this->v = $v; }
}

class CBORLite
{
    private static string $buf;
    private static int $pos;

    public static function decode(string $data): mixed
    {
        self::$buf = $data;
        self::$pos = 0;
        return self::item();
    }

    private static function item(): mixed
    {
        if (self::$pos >= strlen(self::$buf)) return null;

        $b   = ord(self::$buf[self::$pos++]);
        $maj = $b >> 5;
        $add = $b & 0x1f;
        $val = self::additional($add);

        switch ($maj) {
            case 0: return $val;                // unsigned int
            case 1: return -1 - $val;           // negative int
            case 2:                             // byte string
                $s = substr(self::$buf, self::$pos, $val);
                self::$pos += $val;
                return new CBORBytes($s);
            case 3:                             // text string
                $s = substr(self::$buf, self::$pos, $val);
                self::$pos += $val;
                return $s;
            case 4:                             // array
                $a = [];
                for ($i = 0; $i < $val; $i++) $a[] = self::item();
                return $a;
            case 5:                             // map
                $m = [];
                for ($i = 0; $i < $val; $i++) {
                    $k = self::item();
                    $v = self::item();
                    if ($k instanceof CBORBytes) $k = $k->v;
                    $m[is_int($k) ? $k : (string)$k] = $v;
                }
                return $m;
            case 6: return self::item();        // tag → skip, return content
            case 7:                             // simple / float
                if ($add === 20) return false;
                if ($add === 21) return true;
                if ($add === 22) return null;
                return $val;
        }
        return null;
    }

    private static function additional(int $a): int
    {
        if ($a < 24) return $a;
        if ($a === 24) return ord(self::$buf[self::$pos++]);
        if ($a === 25) { $v = unpack('n', substr(self::$buf, self::$pos, 2))[1]; self::$pos += 2; return $v; }
        if ($a === 26) { $v = unpack('N', substr(self::$buf, self::$pos, 4))[1]; self::$pos += 4; return $v; }
        if ($a === 27) { $v = unpack('J', substr(self::$buf, self::$pos, 8))[1]; self::$pos += 8; return $v; }
        return 0;
    }
}
