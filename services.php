<?php
require_once 'config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Fetch only visible services from local DB
$services = $db->query("SELECT * FROM services WHERE visible = 1")->fetch_all(MYSQLI_ASSOC);

// Currency toggle logic
$currency = $_GET['currency'] ?? $_SESSION['currency'] ?? 'usd';
if (isset($_GET['currency'])) {
    $_SESSION['currency'] = $currency;
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

// Build category list for filter
$categories = array_unique(array_filter(array_map(function($s) { return $s['category'] ?? null; }, $services)));
sort($categories);
$selected_category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
// Filter services by category and search
$filtered_services = array_filter($services, function($s) use ($selected_category, $search) {
    $cat = $s['category'] ?? '';
    $name = strtolower($s['name'] ?? '');
    $desc = strtolower($s['description'] ?? '');
    $search = strtolower($search);
    $cat_match = !$selected_category || $cat === $selected_category;
    $search_match = !$search || strpos($name, $search) !== false || strpos($desc, $search) !== false;
    return $cat_match && $search_match;
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo t('available_services'); ?> - SIRTECH SMM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <style>
    body {
        background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
        color: #fff;
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .services-hero {
        padding: 80px 0 40px 0;
        text-align: center;
        background: rgba(0,0,0,0.2);
        margin-bottom: 40px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .services-hero h1 {
        font-size: 3rem;
        font-weight: 700;
        letter-spacing: 1px;
        margin-bottom: 15px;
        text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        background: linear-gradient(90deg, #6a11cb 0%, #2575fc 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .services-hero p {
        color: rgba(255,255,255,0.7);
        font-size: 1.2rem;
        max-width: 700px;
        margin: 0 auto;
    }
    .service-card {
        background: rgba(255,255,255,0.05);
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        padding: 30px;
        margin-bottom: 30px;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        border: 1px solid rgba(255,255,255,0.1);
        height: 100%;
        backdrop-filter: blur(10px);
        position: relative;
        overflow: hidden;
    }
    .service-card::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        transform: rotate(30deg);
        opacity: 0;
        transition: opacity 0.5s;
    }
    .service-card:hover {
        background: rgba(255,255,255,0.08);
        transform: translateY(-8px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.3);
        border-color: rgba(255,255,255,0.2);
    }
    .service-card:hover::before {
        opacity: 1;
    }
    .service-icon {
        font-size: 2.5rem;
        margin-bottom: 20px;
        background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .service-title {
        font-size: 1.4rem;
        font-weight: 600;
        margin-bottom: 12px;
        color: #fff;
    }
    .service-price {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 15px;
        padding: 8px 0;
        border-bottom: 1px dashed rgba(255,255,255,0.2);
    }
    .service-price.usd {
        color: #00e676;
    }
    .service-price.tzs {
        color: #ffca28;
    }
    .service-desc {
        color: rgba(255,255,255,0.8);
        font-size: 1rem;
        margin-bottom: 15px;
        line-height: 1.6;
    }
    .service-details {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.6);
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px dashed rgba(255,255,255,0.1);
    }
    .service-details span {
        margin-right: 10px;
    }
    .filter-bar {
        background: rgba(255,255,255,0.05);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 40px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.1);
    }
    .form-select, .form-control {
        background: rgba(0,0,0,0.3);
        color: #fff;
        border: 1px solid rgba(255,255,255,0.2);
        padding: 12px 15px;
        border-radius: 8px;
        transition: all 0.3s;
    }
    .form-select:focus, .form-control:focus {
        background: rgba(0,0,0,0.4);
        border-color: #2575fc;
        box-shadow: 0 0 0 0.25rem rgba(37, 117, 252, 0.25);
        color: #fff;
    }
    .btn-primary {
        background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
        border: none;
        padding: 10px 25px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s;
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(37, 117, 252, 0.4);
    }
    .btn-outline-light {
        border-color: rgba(255,255,255,0.3);
        color: rgba(255,255,255,0.8);
        transition: all 0.3s;
    }
    .btn-outline-light:hover {
        background: rgba(255,255,255,0.1);
        border-color: rgba(255,255,255,0.5);
        color: #fff;
    }
    .alert-warning {
        background: rgba(255, 193, 7, 0.15);
        border-color: rgba(255, 193, 7, 0.3);
        color: #ffc107;
        backdrop-filter: blur(5px);
    }
    @media (max-width: 767.98px) {
        .services-hero {
            padding: 60px 0 30px 0;
        }
        .services-hero h1 {
            font-size: 2.2rem;
        }
        .services-hero p {
            font-size: 1rem;
        }
        .service-card {
            padding: 20px 15px;
        }
        .filter-bar {
            padding: 15px;
        }
    }
</style>
</head>
<body>
    <div class="services-hero animate__animated animate__fadeInDown">
        <h1><?php echo t('available_services'); ?></h1>
        <p><?php echo t('services_subtitle'); ?></p>
    </div>
    <div class="container">
        <form method="get" class="row filter-bar align-items-end g-2 mb-4">
            <div class="col-md-4 col-12 mb-2 mb-md-0">
                <label for="category" class="form-label"><?php echo t('category'); ?>:</label>
                <select name="category" id="category" class="form-select" onchange="this.form.submit()">
                    <option value=""><?php echo t('all'); ?></option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php if($selected_category===$cat) echo 'selected'; ?>><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5 col-12 mb-2 mb-md-0">
                <label for="search" class="form-label"><?php echo t('search'); ?>:</label>
                <input type="text" name="search" id="search" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="<?php echo t('search_services_placeholder'); ?>">
            </div>
            <div class="col-md-3 col-12 text-md-end text-center">
                <button type="submit" class="btn btn-primary px-4"><?php echo t('search'); ?></button>
                <a href="?currency=<?php echo $currency==='usd'?'tzs':'usd'; ?>" class="btn btn-outline-light ms-2">
                    <?php echo $currency==='usd' ? 'TZS' : 'USD'; ?>
                </a>
            </div>
        </form>
        <?php if (empty($filtered_services)): ?>
            <div class="alert alert-warning text-center my-5"><?php echo t('no_services_available'); ?></div>
        <?php else: ?>
        <div class="row">
            <?php foreach ($filtered_services as $service): ?>
                <div class="col-lg-4 col-md-6 mb-4 d-flex">
                    <div class="service-card w-100 animate__animated animate__fadeInUp">
                        <div class="service-icon"><i class="fas fa-star"></i></div>
                        <div class="service-title"><?php echo htmlspecialchars($service['name'] ?? $service['service']); ?></div>
                        <div class="service-price">
                            <?php if($currency==='tzs') {
                                echo isset($service['price_tzs']) ? 'TZS ' . $service['price_tzs'] : 'N/A';
                            } else {
                                echo isset($service['price_usd']) ? '$' . $service['price_usd'] : 'N/A';
                            } ?>
                        </div>
                        <div class="service-desc"><?php echo htmlspecialchars($service['description'] ?? ''); ?></div>
                        <div class="service-details">
                            <span><b><?php echo t('min'); ?>:</b> <?php echo htmlspecialchars($service['min'] ?? '-'); ?></span> |
                            <span><b><?php echo t('max'); ?>:</b> <?php echo htmlspecialchars($service['max'] ?? '-'); ?></span>
                        </div>
                        <?php if (!empty($service['instructions'])): ?>
                        <div class="service-details mt-2">
                            <b><?php echo t('instructions'); ?>:</b> <?php echo htmlspecialchars($service['instructions']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="text-center mt-4 mb-5">
            <a href="index.php" class="btn btn-outline-primary px-4 py-2"><?php echo t('back_to_home'); ?></a>
        </div>
    </div>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/6c5ce7e7e7.js" crossorigin="anonymous"></script>
    <div class="service-price <?php echo $currency; ?>">
    <?php if($currency==='tzs') {
        echo isset($service['price_tzs']) ? 'TZS ' . $service['price_tzs'] : 'N/A';
    } else {
        echo isset($service['price_usd']) ? '$' . $service['price_usd'] : 'N/A';
    } ?>
</div>
    <?php include 'whatsapp-float.php'; ?>
</body>
</html>
