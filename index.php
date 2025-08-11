<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Language selection logic
$lang = $_GET['lang'] ?? $_SESSION['lang'] ?? 'en';
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $lang;
}
$langs = [
    'en' => require __DIR__ . '/lang_en.php',
    'sw' => require __DIR__ . '/lang_sw.php',
];
function t($key) {
    global $langs, $lang;
    return $langs[$lang][$key] ?? $key;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('welcome_title'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6c5ce7;
            --primary-dark: #5649c0;
            --secondary: #a29bfe;
            --dark: #2d3436;
            --light: #f5f6fa;
            --success: #00b894;
            --info: #0984e3;
            --warning: #fdcb6e;
            --danger: #d63031;
            --glass: rgba(255, 255, 255, 0.05);
            --glass-border: rgba(255, 255, 255, 0.1);
            --text-primary: #ffffff;
            --text-secondary: rgba(255, 255, 255, 0.7);
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: var(--text-primary);
            overflow-x: hidden;
            line-height: 1.6;
        }
        
        /* Glassmorphism Effect */
        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        
        /* Navigation - Modern Glass Navbar */
        .navbar {
            background: rgba(26, 26, 46, 0.8) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 0.8rem 2rem;
            transition: all 0.3s ease;
        }
        
        .navbar.scrolled {
            background: rgba(26, 26, 46, 0.95) !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            padding: 0.5rem 2rem;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
            letter-spacing: 0.5px;
        }
        
        .nav-link {
            position: relative;
            padding: 0.5rem 1rem;
            font-weight: 500;
            color: var(--text-secondary) !important;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--text-primary) !important;
        }
        
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 1rem;
            right: 1rem;
            height: 2px;
            background: var(--primary);
            border-radius: 2px;
        }
        
        /* Hero Section - Animated Gradient Background */
        .hero-section {
            position: relative;
            padding: 160px 0 120px;
            overflow: hidden;
            background: linear-gradient(-45deg, #1a1a2e, #16213e, #6c5ce7, #a29bfe);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        /* Floating Particles Effect */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            overflow: hidden;
        }
        
        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 50%;
            animation: float linear infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; }
        }
        
        /* Buttons - Modern 3D Effect */
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            padding: 12px 30px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(108, 92, 231, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(108, 92, 231, 0.6);
        }
        
        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            z-index: -1;
            transition: opacity 0.3s ease;
            opacity: 0;
        }
        
        .btn-primary:hover::before {
            opacity: 1;
        }
        
        .btn-outline-light {
            border-width: 2px;
            font-weight: 500;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        
        .btn-outline-light:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
        }
        
        /* Feature Cards - 3D Flip Effect */
        .feature-card {
            background: var(--glass);
            border-radius: 16px;
            padding: 40px 30px;
            transition: all 0.5s ease;
            height: 100%;
            perspective: 1000px;
            transform-style: preserve-3d;
        }
        
        .feature-card:hover {
            transform: translateY(-10px) rotateX(5deg);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: var(--primary);
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            transform: scale(1.1);
        }
        
        /* How It Works - Animated Steps */
        .how-it-works {
            counter-reset: step-counter;
            position: relative;
        }
        
        .how-it-works::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            height: 100%;
            width: 2px;
            background: var(--primary);
            z-index: 0;
        }
        
        .how-it-works li {
            position: relative;
            padding-left: 80px;
            margin-bottom: 40px;
            list-style: none;
            z-index: 1;
        }
        
        .how-it-works li::before {
            counter-increment: step-counter;
            content: counter(step-counter);
            position: absolute;
            left: 0;
            top: 0;
            width: 40px;
            height: 40px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            box-shadow: 0 5px 15px rgba(108, 92, 231, 0.5);
            transition: all 0.3s ease;
        }
        
        .how-it-works li:hover::before {
            transform: scale(1.1);
            box-shadow: 0 8px 25px rgba(108, 92, 231, 0.7);
        }
        
        /* Stats Section - Counter Animation */
        .stats-item {
            text-align: center;
            padding: 40px 30px;
        }
        
        .stats-number {
            font-size: 3.5rem;
            font-weight: bold;
            background: linear-gradient(to right, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            line-height: 1;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .stats-item:hover .stats-number {
            transform: scale(1.05);
        }
        
        /* CTA Section - Floating Elements */
        .cta-section {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }
        
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        
        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: floatElement 15s infinite linear;
        }
        
        @keyframes floatElement {
            0% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-50px) rotate(180deg); }
            100% { transform: translateY(0) rotate(360deg); }
        }
        
        /* Footer - Modern Layout */
        .footer {
            background: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }
        
        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://www.transparenttextures.com/patterns/dark-geometric.png');
            opacity: 0.1;
            z-index: -1;
        }
        
        .social-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--glass);
            color: white;
            margin: 0 5px;
            transition: all 0.3s ease;
            border: 1px solid var(--glass-border);
        }
        
        .social-icon:hover {
            transform: translateY(-5px);
            background: var(--primary);
            box-shadow: 0 5px 15px rgba(108, 92, 231, 0.5);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 991.98px) {
            .hero-section {
                padding: 120px 0 80px;
            }
            
            .navbar-collapse {
                background: rgba(26, 26, 46, 0.95);
                backdrop-filter: blur(10px);
                padding: 1rem;
                border-radius: 16px;
                margin-top: 10px;
            }
            
            .nav-link.active::after {
                display: none;
            }
        }
        
        @media (max-width: 767.98px) {
            .hero-section {
                padding: 100px 0 60px;
                text-align: center;
            }
            
            .hero-buttons {
                justify-content: center;
            }
            
            .stats-item {
                padding: 30px 15px;
            }
            
            .stats-number {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 575.98px) {
            .hero-section {
                padding: 80px 0 40px;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-chart-line me-2"></i><?php echo t('brand_name'); ?> <span class="text-primary">SMM</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><?php echo t('home'); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="services.php"><?php echo t('services'); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features"><?php echo t('features'); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#benefits"><?php echo t('benefits'); ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact"><?php echo t('contact'); ?></a>
                    </li>
                    <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                        <a href="register.php" class="btn btn-primary"><?php echo t('sign_up_now'); ?></a>
                    </li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <form method="get" class="d-inline ms-2">
                            <select name="lang" id="lang" class="form-select d-inline w-auto" onchange="this.form.submit()" style="background: var(--glass); color: white; border-color: var(--glass-border);">
                                <option value="en" <?php if($lang==='en') echo 'selected'; ?>><?php echo t('english'); ?></option>
                                <option value="sw" <?php if($lang==='sw') echo 'selected'; ?>><?php echo t('swahili'); ?></option>
                            </select>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <!-- Floating Particles Background -->
        <div class="particles" id="particles"></div>
        
        <div class="container hero-content">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <h1 class="display-3 fw-bold mb-4"><?php echo t('welcome_heading'); ?></h1>
                    <p class="lead mb-5"><?php echo t('welcome_subheading'); ?></p>
                    <div class="d-flex flex-wrap gap-3 hero-buttons">
                        <a href="register.php" class="btn btn-primary btn-lg animate__animated animate__pulse animate__infinite">
                            <?php echo t('sign_up_now'); ?> <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg">
                            <?php echo t('learn_more'); ?> <i class="fas fa-info-circle ms-2"></i>
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block" data-aos="fade-left">
                    <img src="https://cdn.dribbble.com/users/1787323/screenshots/14168330/media/5d8d2e3a65b5c5f0c8e3b0d5d5f5e5e5.gif" alt="<?php echo t('hero_image_alt'); ?>" class="img-fluid animate__animated animate__pulse animate__infinite" style="animation-duration: 2s;">
                </div>
            </div>
        </div>
        
        <!-- Animated scroll indicator -->
        <div class="position-absolute w-100 text-center" style="bottom: 30px; left: 0; z-index: 2;">
            <a href="#features" class="text-white animate__animated animate__bounce animate__infinite" style="display: inline-block;">
                <i class="fas fa-angle-double-down fa-2x"></i>
            </a>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-5 glass-card">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="stats-item">
                        <div class="stats-number" data-count="10000">10K+</div>
                        <div><?php echo t('happy_clients'); ?></div>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="200">
                    <div class="stats-item">
                        <div class="stats-number" data-count="50">50+</div>
                        <div><?php echo t('services'); ?></div>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="stats-item">
                        <div class="stats-number">24/7</div>
                        <div><?php echo t('support'); ?></div>
                    </div>
                </div>
                <div class="col-md-3" data-aos="fade-up" data-aos-delay="400">
                    <div class="stats-item">
                        <div class="stats-number" data-count="99">99%</div>
                        <div><?php echo t('uptime'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold"><?php echo t('explore_services'); ?></h2>
                <p class="text-muted"><?php echo t('discover_services'); ?></p>
            </div>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <h3><?php echo t('instant_delivery'); ?></h3>
                        <p><?php echo t('instant_delivery_desc'); ?></p>
                        <a href="services.php" class="btn btn-sm btn-outline-light mt-3"><?php echo t('view_services'); ?></a>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3><?php echo t('high_quality'); ?></h3>
                        <p><?php echo t('high_quality_desc'); ?></p>
                        <a href="services.php" class="btn btn-sm btn-outline-light mt-3"><?php echo t('view_services'); ?></a>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h3><?php echo t('support'); ?></h3>
                        <p><?php echo t('support_desc'); ?></p>
                        <a href="services.php" class="btn btn-sm btn-outline-light mt-3"><?php echo t('view_services'); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-5 bg-dark">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0" data-aos="fade-right">
                    <img src="https://cdn.dribbble.com/users/1787323/screenshots/6819197/media/420206c7f417d59c0f0feb09a06f7361.gif" alt="<?php echo t('how_it_works_image_alt'); ?>" class="img-fluid rounded">
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <h2 class="display-5 fw-bold mb-4"><?php echo t('how_it_works'); ?></h2>
                    <ol class="how-it-works">
                        <li data-aos="fade-left" data-aos-delay="100"><?php echo t('step1'); ?></li>
                        <li data-aos="fade-left" data-aos-delay="200"><?php echo t('step2'); ?></li>
                        <li data-aos="fade-left" data-aos-delay="300"><?php echo t('step3'); ?></li>
                        <li data-aos="fade-left" data-aos-delay="400"><?php echo t('step4'); ?></li>
                        <li data-aos="fade-left" data-aos-delay="500"><?php echo t('step5'); ?></li>
                    </ol>
                    <a href="register.php" class="btn btn-primary mt-3"><?php echo t('get_started'); ?></a>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section id="benefits" class="py-5">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-5 fw-bold"><?php echo t('why_choose'); ?></h2>
                <p class="text-muted"><?php echo t('real_results'); ?></p>
            </div>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <h3><?php echo t('high_speed_delivery'); ?></h3>
                        <p><?php echo t('high_speed_delivery_desc'); ?></p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h3><?php echo t('secure_private'); ?></h3>
                        <p><?php echo t('secure_private_desc'); ?></p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3><?php echo t('real_analytics'); ?></h3>
                        <p><?php echo t('real_analytics_desc'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 cta-section">
        <div class="floating-elements">
            <div class="floating-element" style="width: 100px; height: 100px; top: 20%; left: 10%; animation-delay: 0s;"></div>
            <div class="floating-element" style="width: 150px; height: 150px; top: 60%; left: 80%; animation-delay: 2s;"></div>
            <div class="floating-element" style="width: 80px; height: 80px; top: 80%; left: 30%; animation-delay: 4s;"></div>
        </div>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center" data-aos="zoom-in">
                    <h2 class="display-5 fw-bold mb-4"><?php echo t('cta_heading'); ?></h2>
                    <p class="lead mb-5"><?php echo t('cta_subheading'); ?></p>
                    <a href="register.php" class="btn btn-light btn-lg px-5 py-3 fw-bold"><?php echo t('get_started_now'); ?></a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer mt-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4 col-md-6 mb-4 mb-lg-0" data-aos="fade-up">
                    <h3 class="mb-4"><i class="fas fa-chart-line me-2"></i><?php echo t('brand_name'); ?> <span class="text-primary">SMM</span></h3>
                    <p><?php echo t('footer_desc'); ?></p>
                    <div class="social-icons mt-4">
                        <a href="#" class="social-icon" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-icon" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-6 mb-4 mb-lg-0" data-aos="fade-up" data-aos-delay="100">
                    <h5 class="mb-4"><?php echo t('quick_links'); ?></h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white"><?php echo t('home'); ?></a></li>
                        <li class="mb-2"><a href="services.php" class="text-white"><?php echo t('services'); ?></a></li>
                        <li class="mb-2"><a href="#features" class="text-white"><?php echo t('features'); ?></a></li>
                        <li class="mb-2"><a href="#benefits" class="text-white"><?php echo t('benefits'); ?></a></li>
                        <li class="mb-2"><a href="#contact" class="text-white"><?php echo t('contact'); ?></a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0" data-aos="fade-up" data-aos-delay="200">
                    <h5 class="mb-4"><?php echo t('services'); ?></h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white"><?php echo t('instagram_growth'); ?></a></li>
                        <li class="mb-2"><a href="#" class="text-white"><?php echo t('youtube_views'); ?></a></li>
                        <li class="mb-2"><a href="#" class="text-white"><?php echo t('twitter_engagement'); ?></a></li>
                        <li class="mb-2"><a href="#" class="text-white"><?php echo t('tiktok_followers'); ?></a></li>
                        <li class="mb-2"><a href="#" class="text-white"><?php echo t('facebook_likes'); ?></a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <h5 class="mb-4"><?php echo t('contact_us'); ?></h5>
                    <ul class="list-unstyled text-white">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i> <?php echo t('address'); ?></li>
                        <li class="mb-2"><i class="fas fa-phone me-2"></i> <?php echo t('phone'); ?></li>
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i> <?php echo t('email'); ?></li>
                    </ul>
                </div>
            </div>
            <hr class="mt-5" style="border-color: rgba(255,255,255,0.1);">
            <div class="text-center py-3">
                <p class="mb-0">© <?php echo date('Y'); ?> <?php echo t('brand_name'); ?> SMM. <?php echo t('all_rights_reserved'); ?></p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS (Animate On Scroll)
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
        
        // Add smooth scrolling to all links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Navbar background change on scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
        
        // Create floating particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 30;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                // Random size between 2px and 6px
                const size = Math.random() * 4 + 2;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                // Random position
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;
                
                // Random animation duration between 10s and 20s
                const duration = Math.random() * 10 + 10;
                particle.style.animationDuration = `${duration}s`;
                
                // Random delay
                particle.style.animationDelay = `${Math.random() * 5}s`;
                
                particlesContainer.appendChild(particle);
            }
        }
        
        // Initialize particles when page loads
        window.addEventListener('load', createParticles);
        
        // Animate stats counters
        function animateCounters() {
            const counters = document.querySelectorAll('.stats-number[data-count]');
            const speed = 200; // Animation speed in ms
            
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                const count = parseInt(counter.innerText.replace('+', ''));
                const increment = target / speed;
                
                if (count < target) {
                    counter.innerText = Math.floor(count + increment) + (counter.innerText.includes('+') ? '+' : '');
                    setTimeout(animateCounters, 1);
                } else {
                    counter.innerText = target + (counter.innerText.includes('+') ? '+' : '');
                }
            });
        }
        
        // Initialize counter animation when stats section is in view
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        document.querySelectorAll('.stats-item').forEach(item => {
            statsObserver.observe(item);
        });
    </script>
    <?php include 'whatsapp-float.php'; ?>
</body>
</html>