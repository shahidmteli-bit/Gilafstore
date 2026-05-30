<?php
/**
 * Contact Us Page
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

$pageTitle = 'Contact Us - Gilaf Store';
$metaDescription = 'Get in touch with Gilaf Store. Reach us via email, phone, or WhatsApp for product inquiries, order support, and business partnerships.';
$activePage = 'contact';

include __DIR__ . '/includes/new-header.php';
?>

<style>
    .contact-hero {
        background-color: var(--color-ivory, #FAF8F5);
        padding: 50px 20px 25px 20px;
        text-align: center;
    }
    .contact-hero-content {
        background: #fff;
        border-radius: 12px;
        padding: 30px 40px;
        max-width: 1200px;
        margin: 0 auto;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(197, 160, 89, 0.2);
    }
    .contact-hero h1 {
        font-size: 3.0rem;
        font-weight: 700;
        margin-bottom: 20px;
        color: #1A3C34;
    }
    .contact-hero p {
        font-size: 1.1rem;
        max-width: 100%;
        margin: 0 auto;
        color: #C9A961;
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    .contact-container {
        max-width: 1100px;
        margin: 0 auto;
        padding: 50px 20px 80px 20px;
    }

    .contact-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
    }

    .contact-card {
        background: #fff;
        border-radius: 12px;
        padding: 35px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        border: 1px solid rgba(197, 160, 89, 0.15);
        text-align: center;
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .contact-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
    }

    .contact-card i {
        font-size: 2.2rem;
        color: #C9A961;
        margin-bottom: 18px;
        display: block;
    }
    .contact-card h3 {
        font-size: 1.3rem;
        color: #1A3C34;
        margin-bottom: 12px;
        font-weight: 700;
    }
    .contact-card p {
        font-size: 1rem;
        color: #555;
        line-height: 1.7;
        margin-bottom: 8px;
    }
    .contact-card a {
        color: #1A3C34;
        font-weight: 600;
        text-decoration: none;
        transition: color 0.3s;
    }
    .contact-card a:hover {
        color: #C9A961;
    }

    .contact-cta {
        text-align: center;
        margin-top: 50px;
        padding: 40px;
        background: #1A3C34;
        border-radius: 12px;
        color: #fff;
    }
    .contact-cta h2 {
        font-size: 1.8rem;
        margin-bottom: 12px;
        color: #fff;
    }
    .contact-cta p {
        font-size: 1.05rem;
        color: rgba(255,255,255,0.85);
        margin-bottom: 25px;
    }
    .contact-cta .btn-ticket {
        display: inline-block;
        padding: 15px 40px;
        background: #C9A961;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 2px;
        font-size: 0.85rem;
        font-weight: 600;
        border-radius: 4px;
        text-decoration: none;
        transition: background 0.3s;
    }
    .contact-cta .btn-ticket:hover {
        background: #b8943f;
    }

    .contact-address {
        text-align: center;
        margin-top: 40px;
        padding: 30px;
        background: #f8f9fa;
        border-radius: 12px;
        border-left: 4px solid #C9A961;
    }
    .contact-address h3 {
        color: #1A3C34;
        margin-bottom: 10px;
        font-size: 1.2rem;
    }
    .contact-address p {
        color: #555;
        font-size: 1rem;
        line-height: 1.8;
    }

    @media (max-width: 768px) {
        .contact-hero h1 { font-size: 2.2rem; }
        .contact-grid { grid-template-columns: 1fr; gap: 20px; }
        .contact-card { padding: 25px; }
        .contact-cta h2 { font-size: 1.4rem; }
    }
</style>

<!-- Hero Section -->
<div class="contact-hero">
    <div class="contact-hero-content">
        <h1>Contact Us</h1>
        <p>We're here to help</p>
    </div>
</div>

<!-- Contact Content -->
<div class="contact-container">

    <div class="contact-grid">
        <div class="contact-card">
            <i class="fas fa-envelope"></i>
            <h3>Email</h3>
            <p>For orders, inquiries & partnerships</p>
            <a href="mailto:support@gilafstore.com">support@gilafstore.com</a>
        </div>

        <div class="contact-card">
            <i class="fab fa-whatsapp"></i>
            <h3>WhatsApp</h3>
            <p>Chat with us directly</p>
            <a href="https://wa.me/919419404670" target="_blank">+91 94194 04670</a>
        </div>

        <div class="contact-card">
            <i class="fas fa-phone-alt"></i>
            <h3>Phone</h3>
            <p>Monday – Saturday, 10 AM – 6 PM IST</p>
            <a href="tel:+919419404670">+91 94194 04670</a>
        </div>

        <div class="contact-card">
            <i class="fas fa-share-alt"></i>
            <h3>Social Media</h3>
            <p>Follow us for updates & offers</p>
            <div style="display: flex; gap: 20px; justify-content: center; margin-top: 10px;">
                <a href="https://www.instagram.com/gilafstore?igsh=MXN5eHg4emhmMWtmZQ==" target="_blank" aria-label="Instagram"><i class="fab fa-instagram" style="font-size: 1.5rem; margin: 0;"></i></a>
                <a href="https://www.facebook.com/share/1D8xHnuELW/" target="_blank" aria-label="Facebook"><i class="fab fa-facebook-f" style="font-size: 1.5rem; margin: 0;"></i></a>
                <a href="https://www.linkedin.com/company/gilafstore/" target="_blank" aria-label="LinkedIn"><i class="fab fa-linkedin-in" style="font-size: 1.5rem; margin: 0;"></i></a>
            </div>
        </div>
    </div>

    <!-- Support Ticket CTA -->
    <div class="contact-cta">
        <h2>Need Help With an Order?</h2>
        <p>Create a support ticket and our team will get back to you within 24 hours.</p>
        <a href="<?= base_url('user/create_ticket.php'); ?>" class="btn-ticket">Create Support Ticket</a>
    </div>

    <!-- Registered Office -->
    <div class="contact-address">
        <h3><i class="fas fa-map-marker-alt"></i> Registered Office</h3>
        <p>Gilaf Foods &amp; Spices<br>
        Sopore, Baramulla<br>
        Jammu &amp; Kashmir – 193201, India</p>
    </div>

</div>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
