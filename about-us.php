<?php
/**
 * About Us Page - Dynamic Content from Database
 */

session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/db_connect.php';

// Fetch About Us content from database
$aboutContent = db_fetch("SELECT * FROM page_content WHERE page_key = 'about_us' AND is_active = 1");
$philosophyContent = db_fetch("SELECT * FROM page_content WHERE page_key = 'our_philosophy' AND is_active = 1");

// Use database content or fallback to defaults
$pageTitle = ($aboutContent && !empty($aboutContent['page_title'])) ? $aboutContent['page_title'] . ' - Gilaf Store' : 'About Us - Gilaf Store';
$metaDescription = ($aboutContent && !empty($aboutContent['meta_description'])) ? $aboutContent['meta_description'] : 'Learn about Gilaf Store - your premier destination for authentic cultural products and artisanal crafts.';

include __DIR__ . '/includes/new-header.php';
?>

<style>
    .about-hero {
        background: linear-gradient(135deg, #C9A961 0%, #D4B76A 20%, #1A3C34 60%, #244A36 100%);
        color: white;
        padding: 120px 20px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .about-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: radial-gradient(circle at 30% 40%, rgba(201, 169, 97, 0.2) 0%, transparent 60%), 
                    radial-gradient(circle at 70% 60%, rgba(26, 60, 52, 0.3) 0%, transparent 60%),
                    url('https://images.unsplash.com/photo-1596040033229-a0b55ee0a1b5?w=1200') center/cover;
        opacity: 0.25;
        z-index: 0;
    }
    .about-hero::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #C9A961 0%, #FFFFFF 50%, #C9A961 100%);
        z-index: 2;
    }
    .about-hero-content {
        position: relative;
        z-index: 1;
    }
    .about-hero h1 {
        font-size: 3.5rem;
        font-weight: 700;
        margin-bottom: 20px;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        font-family: 'Poppins', sans-serif;
    }
    .about-hero p {
        font-size: 1.3rem;
        max-width: 800px;
        margin: 0 auto;
        opacity: 0.95;
        font-family: 'Poppins', sans-serif;
    }
    
    .about-section {
        padding: 80px 20px;
    }
    
    .philosophy-section {
        background: linear-gradient(135deg, #1A3C34 0%, #244A36 100%);
        color: white;
        padding: 100px 20px;
        position: relative;
        overflow: hidden;
    }
    .philosophy-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('https://images.unsplash.com/photo-1599909533730-f9d7e4e7c0e5?w=1200') center/cover;
        opacity: 0.1;
        z-index: 0;
    }
    .philosophy-content {
        max-width: 1200px;
        margin: 0 auto;
        position: relative;
        z-index: 1;
    }
    .philosophy-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 60px;
        align-items: center;
        margin-top: 40px;
    }
    .philosophy-text h2 {
        color: #C9A961;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 3px;
        margin-bottom: 20px;
    }
    .philosophy-text h1 {
        color: #C9A961;
        font-size: 3.5rem;
        font-weight: 700;
        margin-bottom: 30px;
        line-height: 1.2;
    }
    .philosophy-text p {
        font-size: 1.15rem;
        line-height: 1.9;
        margin-bottom: 20px;
        opacity: 0.95;
    }
    .philosophy-text .founders {
        color: #C9A961;
        font-size: 1.3rem;
        font-style: italic;
        margin-top: 40px;
        font-family: 'Georgia', serif;
    }
    .philosophy-image {
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        transform: perspective(1000px) rotateY(-5deg);
        transition: transform 0.3s ease;
    }
    .philosophy-image:hover {
        transform: perspective(1000px) rotateY(0deg);
    }
    .philosophy-image img {
        width: 100%;
        height: 500px;
        object-fit: cover;
        display: block;
    }
    
    .mission-section {
        background: #f8f9fa;
        padding: 80px 20px;
    }
    .mission-content {
        max-width: 1200px;
        margin: 0 auto;
        text-align: center;
    }
    .mission-content h2 {
        font-size: 2.5rem;
        font-weight: 700;
        color: #1A3C34;
        margin-bottom: 40px;
    }
    .mission-content p {
        font-size: 1.2rem;
        line-height: 1.8;
        color: #555;
        max-width: 900px;
        margin: 0 auto;
    }
    
    .values-section {
        padding: 80px 20px;
        background: white;
    }
    .values-content {
        max-width: 1200px;
        margin: 0 auto;
    }
    .values-content h2 {
        font-size: 2.5rem;
        font-weight: 700;
        color: #1A3C34;
        margin-bottom: 50px;
        text-align: center;
    }
    .values-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 30px;
        margin-top: 40px;
    }
    .value-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border: 2px solid #e0e0e0;
        border-radius: 15px;
        padding: 40px 30px;
        text-align: center;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .value-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 5px;
        background: linear-gradient(90deg, #1A3C34 0%, #C9A961 100%);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }
    .value-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 40px rgba(26, 60, 52, 0.15);
        border-color: #C9A961;
    }
    .value-card:hover::before {
        transform: scaleX(1);
    }
    .value-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #1A3C34 0%, #244A36 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 25px;
        font-size: 2rem;
        color: #C9A961;
    }
    .value-card h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1A3C34;
        margin-bottom: 15px;
    }
    .value-card p {
        font-size: 1rem;
        line-height: 1.7;
        color: #666;
    }
    
    .image-gallery {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-top: 50px;
    }
    .gallery-item {
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        transition: transform 0.3s ease;
    }
    .gallery-item:hover {
        transform: scale(1.05);
    }
    .gallery-item img {
        width: 100%;
        height: 250px;
        object-fit: cover;
        display: block;
    }
    
    @media (max-width: 768px) {
        .about-hero h1 { font-size: 2.5rem; }
        .philosophy-grid { grid-template-columns: 1fr; gap: 40px; }
        .philosophy-text h1 { font-size: 2.5rem; }
        .philosophy-image { transform: none; }
        .values-grid { grid-template-columns: 1fr; }
        .image-gallery { grid-template-columns: 1fr; }
    }
</style>

<!-- 1. Hero Section -->
<div class="about-hero">
    <div class="about-hero-content">
        <h1>Gilaf Foods & Spices</h1>
        <p>Delivering Authentic Quality from Source to Kitchen</p>
    </div>
</div>

<!-- 2. Who We Are Section -->
<div class="mission-section">
    <div class="mission-content">
        <h2>Who We Are</h2>
        <p>Gilaf Foods & Spices is a professionally structured organization operating across the global food, dry fruits, tea, beverages, and spices industry. We are committed to delivering high-quality products through authentic sourcing, stringent quality control, and a customer-focused approach. Our operations are guided by international standards, transparency, and a clear vision to establish Gilaf Foods & Spices as a trusted name in quality foods worldwide.</p>
    </div>
</div>

<!-- 3. Why We Exist Section -->
<div class="about-section" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border-top: 3px solid #C9A961; padding: 80px 20px;">
    <div class="mission-content" style="max-width: 850px; margin: 0 auto;">
        <h2>Why We Exist</h2>
        <p>The food, spices, tea, beverages, and dry fruits industry has long faced challenges related to inconsistent quality, unclear sourcing, and limited trust. Gilaf Foods & Spices was established to address these challenges by delivering products that consumers and business partners can rely on with confidence. Our purpose is to set higher industry standards where quality, authenticity, and integrity are non-negotiable, and to build lasting trust through consistent excellence across our product range.</p>
    </div>
</div>

<!-- 4. Our Story Section -->
<div class="philosophy-section">
    <div class="philosophy-content">
        <div class="philosophy-grid">
            <div class="philosophy-text">
                <h2>OUR STORY</h2>
                <h1>Built on Vision, Driven by Purpose</h1>
                <p>Gilaf Foods & Spices began with a simple vision: to create a brand that people could trust. What started as a commitment to quality has grown into a structured organization focused on delivering excellence at every level. Our journey is defined not by timelines, but by milestones, each one reinforcing our dedication to authenticity, consistency, and growth.</p>
                <p>We continue to expand our reach, refine our processes, and strengthen our partnerships, always guided by the belief that quality is the foundation of lasting success.</p>
                <div class="founders">Shahid Mohammad & Muneera Shahid</div>
            </div>
            <div class="philosophy-image">
                <img src="assets/Images/our-story.jpg" alt="Gilaf Premium Tea Products">
            </div>
        </div>
    </div>
</div>

<!-- 5. Leadership Section -->
<div class="about-section" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); padding: 100px 20px;">
    <div class="values-content">
        <div style="text-align: center; margin-bottom: 60px;">
            <h2 style="font-size: 2.8rem; color: #1A3C34; margin-bottom: 15px; font-weight: 700;">Leadership</h2>
            <p style="font-size: 1.2rem; color: #666; max-width: 700px; margin: 0 auto;">Meet the visionaries driving Gilaf Foods & Spices towards excellence</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 50px; max-width: 1400px; margin: 0 auto;">
            
            <!-- CEO Profile -->
            <div style="background: #ffffff; border-radius: 25px; overflow: hidden; box-shadow: 0 20px 60px rgba(26, 60, 52, 0.12); transition: transform 0.3s ease, box-shadow 0.3s ease; position: relative;">
                <div style="position: absolute; top: 0; left: 0; right: 0; height: 8px; background: linear-gradient(90deg, #1A3C34 0%, #C9A961 100%);"></div>
                <div style="padding: 50px 40px;">
                    <div style="display: flex; align-items: center; gap: 35px; margin-bottom: 35px;">
                        <div style="flex-shrink: 0;">
                            <div style="width: 160px; height: 160px; border-radius: 50%; overflow: hidden; border: 6px solid #C9A961; box-shadow: 0 15px 40px rgba(201, 169, 97, 0.3); background: #f5f5f5;">
                                <img src="assets/Images/ceo-shahid-mohammad.jpg" alt="Shahid Mohammad" style="width: 100%; height: 100%; object-fit: cover; object-position: center 20%;" onerror="this.src='https://ui-avatars.com/api/?name=Shahid+Mohammad&size=200&background=1A3C34&color=C9A961&bold=true&font-size=0.4'">
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <h3 style="color: #1A3C34; font-size: 2rem; font-weight: 700; margin-bottom: 8px; letter-spacing: -0.5px;">Shahid Mohammad</h3>
                            <p style="color: #C9A961; font-size: 1.2rem; font-weight: 600; margin-bottom: 12px;">CEO & Founder</p>
                            <div style="display: flex; gap: 12px;">
                                <span style="background: linear-gradient(135deg, #1A3C34 0%, #244A36 100%); color: white; padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">Visionary Leader</span>
                                <span style="background: linear-gradient(135deg, #C9A961 0%, #D4B76A 100%); color: white; padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">Strategist</span>
                            </div>
                        </div>
                    </div>
                    <div style="border-top: 2px solid #f0f0f0; padding-top: 30px;">
                        <p style="font-size: 1.05rem; line-height: 1.9; color: #555; margin-bottom: 20px;">Shahid Mohammad leads Gilaf Foods & Spices with a focus on operational excellence, strategic growth, and building a brand rooted in trust and quality. With a deep understanding of the food and spices industry, he has established the organization on the principles of authenticity, transparency, and unwavering quality standards.</p>
                        <p style="font-size: 1rem; line-height: 1.8; color: #666;">Every decision is guided by long-term vision and a commitment to delivering value to customers, partners, and stakeholders. His leadership philosophy centers on sustainable growth, ethical business practices, and creating lasting impact in the global food and spices market.</p>
                    </div>
                </div>
            </div>
            
            <!-- Silent Partner Profile -->
            <div style="background: #ffffff; border-radius: 25px; overflow: hidden; box-shadow: 0 20px 60px rgba(26, 60, 52, 0.12); transition: transform 0.3s ease, box-shadow 0.3s ease; position: relative;">
                <div style="position: absolute; top: 0; left: 0; right: 0; height: 8px; background: linear-gradient(90deg, #C9A961 0%, #1A3C34 100%);"></div>
                <div style="padding: 50px 40px;">
                    <div style="display: flex; align-items: center; gap: 35px; margin-bottom: 35px;">
                        <div style="flex-shrink: 0;">
                            <div style="width: 160px; height: 160px; border-radius: 50%; overflow: hidden; border: 6px solid #C9A961; box-shadow: 0 15px 40px rgba(201, 169, 97, 0.3); background: linear-gradient(135deg, #1A3C34 0%, #244A36 100%); display: flex; align-items: center; justify-content: center;">
                                <img src="assets/Images/partner-muneera-shahid.jpg" alt="Muneera Shahid" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <i class="fas fa-user-circle" style="font-size: 110px; color: #C9A961; display: none;"></i>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <h3 style="color: #1A3C34; font-size: 2rem; font-weight: 700; margin-bottom: 8px; letter-spacing: -0.5px;">Muneera Shahid</h3>
                            <p style="color: #C9A961; font-size: 1.2rem; font-weight: 600; margin-bottom: 12px;">Silent Partner</p>
                            <div style="display: flex; gap: 12px;">
                                <span style="background: linear-gradient(135deg, #C9A961 0%, #D4B76A 100%); color: white; padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">Strategic Advisor</span>
                                <span style="background: linear-gradient(135deg, #1A3C34 0%, #244A36 100%); color: white; padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600;">Governance</span>
                            </div>
                        </div>
                    </div>
                    <div style="border-top: 2px solid #f0f0f0; padding-top: 30px;">
                        <p style="font-size: 1.05rem; line-height: 1.9; color: #555; margin-bottom: 20px;">Muneera Shahid provides strategic oversight and governance, ensuring that Gilaf Foods & Spices remains aligned with its core values and foundational principles. With a commitment to ethical business practices and long-term vision, she focuses on organizational stability, sustainability, and ensuring the company's continued success.</p>
                        <p style="font-size: 1rem; line-height: 1.8; color: #666;">Her role is instrumental in maintaining the integrity of operations, guiding strategic decisions, and supporting the organization's growth trajectory while preserving the values that define Gilaf Foods & Spices in the global marketplace.</p>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- 6. Vision & Mission Section -->
<div class="mission-section">
    <div class="mission-content">
        <h2>Vision & Mission</h2>
        <div style="max-width: 900px; margin: 40px auto 0; text-align: left;">
            <h3 style="color: #1A3C34; font-size: 1.5rem; margin-bottom: 15px;">Vision</h3>
            <p style="margin-bottom: 40px;">To become a globally recognized brand in the food and spices industry, known for uncompromising quality, authentic sourcing, and customer trust.</p>
            
            <h3 style="color: #1A3C34; font-size: 1.5rem; margin-bottom: 15px;">Mission</h3>
            <p>To deliver premium-quality foods and spices through ethical sourcing, rigorous quality control, and a commitment to excellence. We aim to build lasting relationships with our customers, partners, and suppliers by consistently exceeding expectations.</p>
        </div>
    </div>
</div>

<!-- 7. Quality & Standards Section -->
<div class="about-section">
    <div class="values-content">
        <h2>Quality & Standards</h2>
        <div class="values-grid" style="max-width: 1000px; margin: 40px auto 0;">
            <div class="value-card">
                <div class="value-icon"><i class="fas fa-check-circle"></i></div>
                <h3>Quality Control</h3>
                <p>Every product undergoes strict quality checks at multiple stages to ensure consistency and excellence.</p>
            </div>
            <div class="value-card">
                <div class="value-icon"><i class="fas fa-shield-alt"></i></div>
                <h3>Safety</h3>
                <p>We adhere to food safety standards and best practices to protect the health and well-being of our customers.</p>
            </div>
            <div class="value-card">
                <div class="value-icon"><i class="fas fa-clipboard-check"></i></div>
                <h3>Compliance</h3>
                <p>Our operations meet industry regulations and standards, ensuring full compliance and accountability.</p>
            </div>
            <div class="value-card">
                <div class="value-icon"><i class="fas fa-balance-scale"></i></div>
                <h3>Consistency</h3>
                <p>We maintain uniformity in quality, taste, and packaging across all products and deliveries.</p>
            </div>
            <div class="value-card">
                <div class="value-icon"><i class="fas fa-qrcode"></i></div>
                <h3>Traceability</h3>
                <p>Track authenticity and verify product origin with our advanced batch tracking system for complete transparency.</p>
            </div>
            <div class="value-card">
                <div class="value-icon"><i class="fas fa-headset"></i></div>
                <h3>24/7 Support</h3>
                <p>Our dedicated customer support team is available around the clock to assist you with any queries or concerns.</p>
            </div>
        </div>
    </div>
</div>

<!-- 8. Closing Statement Section -->
<div class="philosophy-section" style="padding: 80px 20px; background: linear-gradient(135deg, #1A3C34 0%, #2A5C4A 25%, #C9A961 75%, #D4B76A 100%); position: relative; overflow: hidden;">
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at 20% 50%, rgba(201, 169, 97, 0.15) 0%, transparent 50%), radial-gradient(circle at 80% 50%, rgba(26, 60, 52, 0.15) 0%, transparent 50%); pointer-events: none;"></div>
    <div class="philosophy-content" style="text-align: center; max-width: 900px; margin: 0 auto; position: relative; z-index: 1;">
        <h2 style="color: #FFFFFF; font-size: 2.5rem; margin-bottom: 25px; font-weight: 700; text-shadow: 2px 2px 8px rgba(0,0,0,0.3);">Building Trust, Delivering Excellence</h2>
        <p style="font-size: 1.25rem; line-height: 1.9; color: #FFFFFF; text-shadow: 1px 1px 4px rgba(0,0,0,0.2); font-weight: 400;">At Gilaf Foods & Spices, we are committed to long-term growth built on trust, quality, and integrity. As we expand our reach, we remain focused on what matters most: delivering products that meet the highest standards and exceed customer expectations. This is our promise, and it guides everything we do.</p>
        <div style="margin-top: 35px; padding-top: 30px; border-top: 2px solid rgba(255,255,255,0.3);">
            <p style="font-size: 1.1rem; color: #FFFFFF; font-style: italic; opacity: 0.95;">Your trust is our foundation. Your satisfaction is our success.</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/new-footer.php'; ?>
