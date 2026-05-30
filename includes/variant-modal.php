<?php if (!defined('BASE_PATH')) { /* safety: only include via PHP */ } ?>
<!-- ═══ Variant Selector Modal ═══ -->
<div id="gfvOverlay" class="gfv-overlay" aria-hidden="true"></div>
<div id="gfvModal" class="gfv-modal" role="dialog" aria-modal="true" aria-labelledby="gfvModalName">
  <button id="gfvClose" class="gfv-close" aria-label="Close">&times;</button>

  <div class="gfv-header">
    <div class="gfv-img-wrap">
      <img id="gfvImg" src="" alt="" loading="lazy">
    </div>
    <div class="gfv-info">
      <p class="gfv-name" id="gfvModalName"></p>
      <div class="gfv-price-row">
        <span class="gfv-price" id="gfvPrice">—</span>
        <span class="gfv-orig" id="gfvOrig"></span>
      </div>
    </div>
  </div>

  <div class="gfv-divider"></div>

  <p class="gfv-label">Select Size</p>
  <div class="gfv-chips-wrap">
    <span id="gfvModalHand" class="gfv-modal-hand" aria-hidden="true"><span class="gf-hand-inner"><i class="fas fa-hand-pointer"></i></span></span>
    <div class="gfv-chips" id="gfvChips"></div>
  </div>
  <p class="gfv-hint" id="gfvHint"><i class="fas fa-info-circle"></i> Please select a size to continue</p>

  <div class="gfv-qty-row">
    <span class="gfv-qty-label">Quantity</span>
    <div class="gfv-qty-ctrl">
      <button type="button" id="gfvQtyMinus" class="gfv-qty-btn" aria-label="Decrease">&#8722;</button>
      <span id="gfvQtyVal">1</span>
      <button type="button" id="gfvQtyPlus" class="gfv-qty-btn" aria-label="Increase">&#43;</button>
    </div>
  </div>

  <form id="gfvForm" action="<?= base_url('ajax-add-to-cart.php'); ?>" method="post">
    <input type="hidden" name="ajax" value="1">
    <input type="hidden" name="product_id" id="gfvProductId">
    <input type="hidden" name="weight_id" id="gfvWeightId">
    <input type="hidden" name="quantity" id="gfvQtyInput" value="1">
    <button type="submit" id="gfvAddBtn" class="gfv-add-btn" disabled>
      <i class="fas fa-shopping-cart"></i> Add to Cart
    </button>
  </form>
</div>

<!-- Added-to-cart toast -->
<div id="gfvToast" class="gfv-toast" aria-live="polite">
  <i class="fas fa-check-circle"></i> Added to cart!
</div>

<style>
/* ── Overlay ── */
.gfv-overlay{position:fixed;inset:0;background:rgba(0,0,0,.52);z-index:9000;opacity:0;pointer-events:none;transition:opacity .3s ease}
.gfv-overlay.open{opacity:1;pointer-events:all}

/* ── Modal – bottom sheet base ── */
.gfv-modal{position:fixed;z-index:9001;background:#fff;width:100%;max-width:480px;bottom:0;left:50%;transform:translateX(-50%) translateY(100%);transition:transform .38s cubic-bezier(.34,1.4,.64,1);border-radius:24px 24px 0 0;padding:28px 24px 36px;box-shadow:0 -6px 40px rgba(0,0,0,.15);max-height:92dvh;overflow-y:auto}
.gfv-modal.open{transform:translateX(-50%) translateY(0)}

/* Desktop: centered card */
@media(min-width:640px){
  .gfv-modal{border-radius:20px;bottom:auto;top:50%;transform:translateX(-50%) translateY(-50%) scale(.92);opacity:0;transition:transform .3s ease,opacity .3s ease;max-height:88vh}
  .gfv-modal.open{transform:translateX(-50%) translateY(-50%) scale(1);opacity:1}
}

/* Close btn */
.gfv-close{position:absolute;top:14px;right:16px;width:34px;height:34px;border-radius:50%;border:none;background:#f2f0eb;color:#555;font-size:1.25rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s;line-height:1}
.gfv-close:hover{background:#e4e1da}

/* Header */
.gfv-header{display:flex;gap:16px;align-items:flex-start;margin-bottom:18px}
.gfv-img-wrap{width:82px;height:82px;border-radius:14px;overflow:hidden;border:1px solid #ede8df;background:#f9f7f2;flex-shrink:0}
.gfv-img-wrap img{width:100%;height:100%;object-fit:contain;padding:6px}
.gfv-info{flex:1}
.gfv-name{font-family:'Playfair Display',serif;font-size:.98rem;font-weight:700;color:#1A3C34;margin:0 0 10px;line-height:1.3;padding-right:28px}
.gfv-price-row{display:flex;align-items:center;gap:10px}
.gfv-price{font-size:1.22rem;font-weight:700;color:#1A3C34}
.gfv-orig{font-size:.85rem;color:#aaa;text-decoration:line-through}

/* Divider */
.gfv-divider{height:1px;background:#f0ebe2;margin:0 0 18px}

/* Section label */
.gfv-label{font-size:.75rem;font-weight:800;letter-spacing:.7px;text-transform:uppercase;color:#999;margin:0 0 12px}

/* Chips */
.gfv-chips{display:flex;flex-wrap:nowrap;gap:8px;margin-bottom:10px}
.gfv-chip{padding:9px 10px;border:2px solid #dbd6cc;border-radius:50px;background:#fff;font-size:.83rem;font-weight:600;color:#555;cursor:pointer;transition:all .2s ease;flex:1 1 0;min-width:0;text-align:center;white-space:nowrap;}
.gfv-chip:hover{border-color:#1A3C34;color:#1A3C34}
.gfv-chip.selected{background:linear-gradient(135deg,#1f4d3a 0%,#2d6a4f 55%,#c6a55b 100%);border-color:transparent;color:#fff;box-shadow:0 4px 14px rgba(31,77,58,.35)}

/* Hint */
.gfv-hint{font-size:.78rem;color:#C5A059;margin:4px 0 18px;display:flex;align-items:center;gap:6px}

/* Qty row */
.gfv-qty-row{display:flex;align-items:center;justify-content:space-between;background:#f9f7f2;border-radius:14px;padding:12px 18px;margin-bottom:20px}
.gfv-qty-label{font-size:.88rem;font-weight:600;color:#444}
.gfv-qty-ctrl{display:flex;align-items:center;gap:16px}
.gfv-qty-btn{width:34px;height:34px;border-radius:50%;border:2px solid #1A3C34;background:#fff;color:#1A3C34;font-size:1.1rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s;line-height:1}
.gfv-qty-btn:hover{background:#1A3C34;color:#fff}
#gfvQtyVal{font-size:1rem;font-weight:700;color:#1A3C34;min-width:22px;text-align:center}

/* Add btn — premium red */
.gfv-add-btn{width:100%;padding:15px;background:linear-gradient(135deg,#7f0000 0%,#b71c1c 45%,#e53935 100%);color:#fff;border:none;border-radius:50px;font-size:.95rem;font-weight:700;letter-spacing:.6px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:all .3s ease;box-shadow:0 5px 22px rgba(127,0,0,.45),0 2px 8px rgba(229,57,53,.3);position:relative;overflow:hidden}
.gfv-add-btn::before{content:'';position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.18),transparent);transform:skewX(-20deg);transition:left .6s ease;pointer-events:none}
.gfv-add-btn:not(:disabled):hover::before{left:130%}
.gfv-add-btn:not(:disabled):hover{transform:translateY(-2px) scale(1.02);box-shadow:0 10px 32px rgba(229,57,53,.55),0 4px 12px rgba(127,0,0,.4);filter:brightness(1.08)}
.gfv-add-btn:disabled{background:#d5d2cc;box-shadow:none;cursor:not-allowed}

/* Modal traversing hand — outer positioned by JS, inner bounces via CSS */
.gfv-chips-wrap{position:relative;padding-bottom:36px;}
.gfv-modal-hand{position:absolute;top:0;left:0;pointer-events:none;transition:transform .55s cubic-bezier(.4,0,.2,1),opacity .4s ease;z-index:5;will-change:transform;opacity:0;}
.gfv-modal-hand.gfv-active{opacity:1;}
.gfv-modal-hand .gf-hand-inner{display:block;font-size:1.15rem;color:#111;filter:drop-shadow(0 -2px 6px rgba(0,0,0,.28));animation:gfMHandBounce 1s ease-in-out infinite;will-change:transform;}
@keyframes gfMHandBounce{0%,100%{transform:translateY(0);}50%{transform:translateY(-6px);}}
.gfv-chip.gf-hover-hint{box-shadow:0 0 0 3px rgba(0,0,0,.18)!important;border-color:#333!important;}

/* Toast */
.gfv-toast{position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(90px);background:#1A3C34;color:#fff;padding:13px 28px;border-radius:50px;font-size:.9rem;font-weight:600;display:flex;align-items:center;gap:10px;box-shadow:0 8px 30px rgba(26,60,52,.4);z-index:9002;transition:transform .4s cubic-bezier(.34,1.4,.64,1),opacity .4s;opacity:0;pointer-events:none;white-space:nowrap}
.gfv-toast.show{transform:translateX(-50%) translateY(0);opacity:1}
.gfv-toast i{color:#c6a55b}
</style>

<script>
(function(){
  var overlay  = document.getElementById('gfvOverlay');
  var modal    = document.getElementById('gfvModal');
  var closeBtn = document.getElementById('gfvClose');
  var chipsBox = document.getElementById('gfvChips');
  var img      = document.getElementById('gfvImg');
  var name     = document.getElementById('gfvModalName');
  var price    = document.getElementById('gfvPrice');
  var orig     = document.getElementById('gfvOrig');
  var hint     = document.getElementById('gfvHint');
  var qtyVal   = document.getElementById('gfvQtyVal');
  var addBtn   = document.getElementById('gfvAddBtn');
  var pidInput = document.getElementById('gfvProductId');
  var widInput = document.getElementById('gfvWeightId');
  var qtyInput = document.getElementById('gfvQtyInput');
  var form     = document.getElementById('gfvForm');
  var toast    = document.getElementById('gfvToast');
  var mHand    = document.getElementById('gfvModalHand');

  var qty = 1;
  var selectedVariant = null;
  var mTimer = null, mStopped = false;

  /* ── Open modal ── */
  document.querySelectorAll('.gf-open-variant-modal').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.stopPropagation();
      var variants = JSON.parse(this.getAttribute('data-variants') || '[]');
      openModal(
        this.getAttribute('data-product-id'),
        this.getAttribute('data-product-name'),
        this.getAttribute('data-product-image'),
        variants
      );
    });
  });

  function openModal(pid, pname, pimage, variants){
    selectedVariant = null;
    qty = 1;
    pidInput.value     = pid;
    widInput.value     = '';
    qtyInput.value     = 1;
    qtyVal.textContent = '1';
    addBtn.disabled    = true;
    price.textContent  = '—';
    orig.textContent   = '';
    hint.style.display = 'flex';
    name.textContent   = pname;
    img.src  = pimage;
    img.alt  = pname;

    /* Build chips */
    chipsBox.innerHTML = '';
    variants.forEach(function(v){
      var chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'gfv-chip';
      chip.textContent = v.label;
      chip.addEventListener('click', function(){
        chipsBox.querySelectorAll('.gfv-chip').forEach(function(c){ c.classList.remove('selected'); });
        this.classList.add('selected');
        selectVariant(v);
      });
      chipsBox.appendChild(chip);
    });

    overlay.classList.add('open');
    modal.classList.add('open');
    overlay.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';

    startModalHand();
  }

  /* ── Traversing hand ── */
  function startModalHand(){
    mStopped = false;
    clearTimeout(mTimer);
    var chips = Array.prototype.slice.call(chipsBox.querySelectorAll('.gfv-chip'));
    if (!mHand || chips.length < 2) { if(mHand) mHand.style.opacity='0'; return; }
    var midx = 0;
    function mGoTo(i){
      var chip = chips[i]; if(!chip) return;
      var wr = mHand.parentElement.getBoundingClientRect();
      var cr = chip.getBoundingClientRect();
      var x = (cr.left - wr.left) + (cr.width/2) - 12;
      var y = (cr.bottom - wr.top) + 6;
      mHand.style.transform = 'translateX('+x+'px) translateY('+y+'px)';
      chips.forEach(function(c){c.classList.remove('gf-hover-hint');});
      chip.classList.add('gf-hover-hint');
    }
    function mNext(){ if(mStopped)return; mGoTo(midx); midx=(midx+1)%chips.length; mTimer=setTimeout(mNext,1500); }
    mHand.classList.add('gfv-active');
    setTimeout(mNext, 500);
  }

  function stopModalHand(){
    mStopped = true;
    clearTimeout(mTimer);
    chipsBox.querySelectorAll('.gfv-chip').forEach(function(c){c.classList.remove('gf-hover-hint');});
    if(mHand){ mHand.classList.remove('gfv-active'); }
  }

  function selectVariant(v){
    stopModalHand();
    selectedVariant = v;
    widInput.value    = v.id;
    price.textContent = v.price;
    orig.textContent  = v.orig || '';
    hint.style.display = 'none';
    addBtn.disabled   = false;
    addBtn.innerHTML  = '<i class="fas fa-shopping-cart"></i> Add to Cart';
  }

  function closeModal(){
    stopModalHand();
    overlay.classList.remove('open');
    modal.classList.remove('open');
    overlay.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
  }

  closeBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', function(e){ if(e.target === this) closeModal(); });
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeModal(); });

  /* ── Qty controls ── */
  document.getElementById('gfvQtyMinus').addEventListener('click', function(){
    if(qty > 1){ qty--; qtyVal.textContent = qty; qtyInput.value = qty; }
  });
  document.getElementById('gfvQtyPlus').addEventListener('click', function(){
    qty++; qtyVal.textContent = qty; qtyInput.value = qty;
  });

  /* ── AJAX form submit ── */
  form.addEventListener('submit', function(e){
    e.preventDefault();
    if(!selectedVariant) return;

    var fd = new FormData(this);
    addBtn.disabled = true;
    addBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding…';

    fetch(this.action, { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){ return r.text(); })
      .then(function(text){
        var data;
        try { data = JSON.parse(text); } catch(ex) {
          console.error('[GFV] JSON parse failed:', text);
          addBtn.disabled = false;
          addBtn.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
          return;
        }
        if(data.success){
          closeModal();
          showToast();
          bumpCartCount();
        } else {
          console.error('[GFV] Server error:', data.error);
          addBtn.disabled = false;
          addBtn.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
        }
      })
      .catch(function(err){
        console.error('[GFV] Fetch failed:', err);
        addBtn.disabled = false;
        addBtn.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
      });
  });

  function showToast(){
    toast.classList.add('show');
    setTimeout(function(){ toast.classList.remove('show'); }, 3000);
  }

  function bumpCartCount(){
    var badge = document.getElementById('cartCountBadge');
    if(badge){
      var n = parseInt(badge.textContent) || 0;
      badge.textContent = n + qty;
      badge.style.display = 'flex';
    }
  }
})();
</script>
