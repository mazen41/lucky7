<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lucky 7 Cafe - Where Everything Goes Your Way</title>

    <?php
        // --- Cafe Information ---
        $cafeName = "Lucky 7";
        $slogan = "Where Everything Goes Your Way";
        $description = "Welcome to Lucky 7, your neighborhood spot for specialty coffee, delightful pastries, and a wide array of fresh and flavorful meals. Discover your new favorite today.";
        
        // --- Links ---
        $instagramLink = "https://www.instagram.com/lucky7.cafe?igsh=MWh2NThoYml5Z3doaA%3D%3D&utm_source=qr";
        $facebookLink = "https://www.facebook.com/share/1FW5Fpztjt/?mibextid=wwXIfr";
        $tiktokLink = "https://www.tiktok.com/@lucky7coffeeshop7?_r=1&_t=ZS-92tngHGRFBZ";
        $menuLink = "https://lucky7cafe.kesug.com/menu.pdf";
    ?>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Roboto:wght@300;400&display=swap" rel="stylesheet">

    <!-- Font Awesome (for icons) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- AOS (Animate on Scroll) Library -->
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />

    <style>
        /* --- Color Palette Variables --- */
        :root {
            --bg-color: #FAF3EB;
            --primary-text: #5F3813;
            --accent-purple: #480335;
            --accent-teal: #38C296;
            --accent-tan: #A87B4F;
        }

        /* --- General Styling --- */
        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--bg-color);
            color: var(--primary-text);
            margin: 0;
            line-height: 1.8;
            font-weight: 300;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
            text-align: center;
        }

        /* --- Header & Hero Section --- */
        .hero {
            padding: 60px 20px; /* Adjusted padding */
            background: linear-gradient(rgba(0,0,0,0.1), rgba(0,0,0,0.1)), url('https://images.unsplash.com/photo-1554118811-1e0d58224f24?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2047&q=80') no-repeat center center/cover;
            background-attachment: fixed;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        /* --- NEW: Logo Styling --- */
        .logo-img {
            width: 140px;
            height: auto;
            margin-bottom: 25px;
            /* Optional: if your logo looks good with a border */
            /* border-radius: 50%; */
            /* background: rgba(255, 255, 255, 0.8); */
            /* padding: 10px; */
            /* box-shadow: 0 4px 10px rgba(0,0,0,0.3); */
        }

        h1.cafe-name {
            font-family: 'Playfair Display', serif;
            font-size: 4.5rem; /* Slightly smaller to accommodate logo */
            color: #fff;
            text-shadow: 2px 2px 5px rgba(0,0,0,0.5);
            margin: 0;
        }

        p.slogan {
            font-size: 1.5rem;
            color: #fff;
            font-weight: 300;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
            margin-top: 10px;
        }

        /* --- Section Styling --- */
        .section {
            padding: 60px 0;
        }
        
        h2 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            color: var(--accent-purple);
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }

        h2::after {
            content: '';
            position: absolute;
            width: 60%;
            height: 2px;
            background-color: var(--accent-tan);
            bottom: -10px;
            left: 20%;
        }

        .section p {
            max-width: 650px;
            margin: 0 auto 30px auto;
            font-size: 1.1rem;
        }

        /* --- Services/Specialty Section --- */
        .services {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            margin-top: 40px;
        }

        .service-item {
            width: 200px;
        }

        .service-item i {
            font-size: 3rem;
            color: var(--accent-teal);
            margin-bottom: 15px;
            transition: transform 0.3s ease;
        }

        .service-item:hover i {
            transform: scale(1.2);
        }

        .service-item h3 {
            font-size: 1.2rem;
            color: var(--primary-text);
            margin: 0;
        }
        
        /* --- Menu Button --- */
        .menu-button {
            display: inline-block;
            padding: 15px 40px;
            background-color: var(--accent-teal);
            color: var(--bg-color);
            text-decoration: none;
            font-size: 1.2em;
            font-weight: bold;
            border-radius: 50px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .menu-button:hover {
            background-color: var(--accent-purple);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }

        /* --- Social Links --- */
        .social-links {
            margin-top: 40px;
        }
        
        .social-links a {
            font-size: 2.5rem;
            margin: 0 20px;
            color: var(--accent-tan);
            transition: color 0.3s ease, transform 0.3s ease;
        }

        .social-links a:hover {
            color: var(--accent-purple);
            transform: scale(1.1);
        }

        /* --- Footer --- */
        footer {
            padding: 20px;
            font-size: 0.9rem;
        }

    </style>
</head>
<body>

    <header class="hero" data-aos="fade-in" data-aos-duration="1000">
        <!-- MODIFIED SECTION: Logo added here -->
        <img src="/assets/images/logo.png" alt="<?php echo $cafeName; ?> Logo" class="logo-img" data-aos="zoom-in" data-aos-delay="200">
        <h1 class="cafe-name" data-aos="fade-up" data-aos-delay="400"><?php echo $cafeName; ?></h1>
        <p class="slogan" data-aos="fade-up" data-aos-delay="600"><?php echo $slogan; ?></p>
        <!-- END MODIFICATION -->
    </header>

    <div class="container">

        <section class="section about" id="about">
            <h2 data-aos="fade-up">About Us</h2>
            <p data-aos="fade-up" data-aos-delay="100">
                <?php echo $description; ?>
            </p>
            <div class="services">
                <div class="service-item" data-aos="fade-up" data-aos-delay="200">
                    <i class="fas fa-coffee"></i>
                    <h3>Specialty Coffee</h3>
                </div>
                <div class="service-item" data-aos="fade-up" data-aos-delay="300">
                    <i class="fas fa-cookie-bite"></i>
                    <h3>Fresh Bakery</h3>
                </div>
                <div class="service-item" data-aos="fade-up" data-aos-delay="400">
                    <i class="fas fa-utensils"></i>
                    <h3>Sandwiches & Salads</h3>
                </div>
            </div>
        </section>

        <section class="section menu" id="menu">
            <h2 data-aos="fade-up">Our Menu</h2>
            <p data-aos="fade-up" data-aos-delay="100">
                Ready to explore a world of flavor? Our full menu is just a click away.
            </p>
            <a href="<?php echo $menuLink; ?>" class="menu-button" target="_blank" data-aos="zoom-in" data-aos-delay="200">View Full Menu</a>
        </section>

        <section class="section connect" id="connect">
            <h2 data-aos="fade-up">Connect With Us</h2>
            <p data-aos="fade-up" data-aos-delay="100">
                Follow our journey and stay updated with our latest creations on social media.
            </p>
            <div class="social-links">
                <a href="<?php echo $instagramLink; ?>" target="_blank" data-aos="fade-up" data-aos-delay="200" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                <a href="<?php echo $facebookLink; ?>" target="_blank" data-aos="fade-up" data-aos-delay="300" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="<?php echo $tiktokLink; ?>" target="_blank" data-aos="fade-up" data-aos-delay="400" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
            </div>
        </section>

    </div>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> <?php echo $cafeName; ?> Cafe. All Rights Reserved.</p>
    </footer>

    <!-- AOS (Animate on Scroll) JavaScript -->
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
        AOS.init({
            once: true, // Whether animation should happen only once - while scrolling down
            duration: 800, // values from 0 to 3000, with step 50ms
            startEvent: 'DOMContentLoaded', // name of the event dispatched on the document, that AOS should initialize on
        });
    </script>

</body>
</html>