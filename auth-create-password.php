<!DOCTYPE html>
<html lang="en" class="h-100">


<!-- auth-create-password.html Ownuh, 16:37 GMT -->
<head>
    <meta charset="utf-8">
    <title>Create Password | Ownuh - Secure Authentication and Intrusion Prevention System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Ownuh is the top-selling Bootstrap 5 admin dashboard template. With Dark Mode, multi-demo options, RTL support, and lifetime updates, it's perfect for web developers.">
    <meta name="keywords" content="Ownuh bootstrap dashboard, bootstrap, bootstrap 5, html dashboard, web dashboard, admin themes, web design, figma, web development, fullcalendar, datatables, free templates, free admin themes, bootstrap theme, bootstrap template, bootstrap dashboard, bootstrap dark mode, bootstrap button, frontend dashboard, responsive bootstrap theme">
    <meta content="Ownuh" name="author">
    <link rel="shortcut icon" href="assets/images/Favicon.png">
    
    <meta property="og:locale" content="en_US">
    <meta property="og:type" content="article">
    <meta property="og:title" content="Ownuh - Secure Authentication and Intrusion Prevention System">
    <meta property="og:description" content="Ownuh is the top-selling Bootstrap 5 admin dashboard template. With Dark Mode, multi-demo options, RTL support, and lifetime updates, it's perfect for web developers.">
    <meta property="og:url" content="https://themeforest.net/user/Ownuh/portfolio">
    <meta property="og:site_name" content="by Ownuhs">
    <script>const AUTH_LAYOUT = true;</script>
    <!-- Layout JS -->
    <script src="assets/js/layout/layout-auth.js"></script>
    
    <script src="assets/js/layout/layout.js"></script>
    
    <!-- Choice Css -->
    <link rel="stylesheet" href="assets/libs/choices.js/public/assets/styles/choices.min.css">
    <!-- Simplebar Css -->
    <link href="assets/libs/simplebar/simplebar.min.css" rel="stylesheet">
    <!--icons css-->
    <link href="assets/css/icons.min.css" rel="stylesheet" type="text/css">
    <!-- Sweet Alert -->
    <link href="assets/libs/sweetalert2/sweetalert2.min.css" rel="stylesheet" type="text/css">
    <!-- Bootstrap Css -->
    <link href="assets/css/bootstrap.min.css" id="bootstrap-style" rel="stylesheet" type="text/css">
    <!-- App Css-->
    <link href="assets/css/app.min.css" id="app-style" rel="stylesheet" type="text/css">
    
    <link href="assets/css/custom.min.css" id="custom-style" rel="stylesheet" type="text/css">
</head>

<body>

    <!-- START HEADER -->
    <header class="app-header">
      <div class="container-fluid">
        <div class="nav-header">
    
          <div class="header-left hstack gap-3">
            <!-- HORIZONTAL BRAND LOGO -->
            <div class="app-sidebar-logo app-horizontal-logo justify-content-center align-items-center">
              <a href="index.html">
                <img height="70" class="app-sidebar-logo-default" alt="Logo" loading="lazy" src="assets/images/light-logo.png">
                <img height="40" class="app-sidebar-logo-minimize" alt="Logo" loading="lazy" src="assets/images/Favicon.png">
              </a>
            </div>
    
            <!-- Sidebar Toggle Btn -->
            <button type="button" class="btn btn-light-light icon-btn sidebar-toggle d-none d-md-block" aria-expanded="false" aria-controls="main-menu">
              <span class="visually-hidden">Toggle sidebar</span>
              <i class="ri-menu-2-fill"></i>
            </button>
    
            <!-- Sidebar Toggle for Mobile -->
            <button class="btn btn-light-light icon-btn d-md-none small-screen-toggle" id="smallScreenSidebarLabel" type="button" data-bs-toggle="offcanvas" data-bs-target="#smallScreenSidebar" aria-controls="smallScreenSidebar">
              <span class="visually-hidden">Sidebar toggle for mobile</span>
              <i class="ri-arrow-right-fill"></i>
            </button>
    
            <!-- Sidebar Toggle for Horizontal Menu -->
            <button class="btn btn-light-light icon-btn d-lg-none small-screen-horizontal-toggle" type="button" ria-expanded="false" aria-controls="main-menu">
              <span class="visually-hidden">Sidebar toggle for horizontal</span>
              <i class="ri-arrow-right-fill"></i>
            </button>
    
            <!-- Search Dropdown -->
            <div class="dropdown features-dropdown">
    
              <!-- Search Input for Desktop -->
              <form class="d-none d-sm-block header-search" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="form-icon">
                  <input type="search" class="form-control form-control-icon" id="firstNameLayout4" placeholder="Search in Ownuh" required>
                  <i class="ri-search-2-line text-muted"></i>
                </div>
              </form>
    
              <!-- Search Button for Mobile -->
              <button type="button" class="btn btn-light-light icon-btn d-sm-none" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="visually-hidden">Search</span>
                <i class="ri-search-2-line text-muted"></i>
              </button>
    
              <div class="dropdown-menu">
                <span class="dropdown-header fs-12">Recent searches</span>
                <div class="dropdown-item text-wrap bg-transparent">
                  <span class="badge bg-light text-muted me-2">Gulp</span>
                  <span class="badge bg-light text-muted me-2">Notification panel</span>
                </div>
                <div class="dropdown-divider"></div>
                <span class="dropdown-header fs-12">Tutorials</span>
                <div class="dropdown-item">
                  <div class="hstack gap-2 overflow-hidden">
                    <button type="button" class="btn btn-light-light rounded-pill icon-btn-sm flex-shrink-0">
                      <span class="visually-hidden">Equalizer settings</span>
                      <i class="ri-equalizer-3-line m-0"></i>
                    </button>
                    <div class="flex-grow-1 text-truncate">
                      <span>How to set up Gulp?</span>
                    </div>
                  </div>
                </div>
                <div class="dropdown-item">
                  <div class="hstack gap-2 overflow-hidden">
                    <button type="button" class="btn btn-light-light rounded-pill icon-btn-sm flex-shrink-0">
                      <span class="visually-hidden">How to change theme color?</span>
                      <i class="ri-palette-line m-0"></i>
                    </button>
                    <div class="flex-grow-1 text-truncate">
                      <span>How to change theme color?</span>
                    </div>
                  </div>
                </div>
                <div class="dropdown-divider"></div>
                <span class="dropdown-header fs-12">Members</span>
                <div class="dropdown-item">
                  <div class="hstack gap-2 overflow-hidden">
                    <div class="flex-shrink-0">
                      <img class="img-fluid avatar-sm avatar-item" src="assets/images/avatar/avatar-10.jpg" alt="avatar image">
                    </div>
                    <div class="flex-grow-1 text-truncate">
                      <span>Amanda Harvey <i class="bi-patch-check-fill text-primary"></i></span>
                    </div>
                  </div>
                </div>
                <div class="dropdown-item">
                  <div class="hstack gap-2 overflow-hidden">
                    <div class="flex-shrink-0">
                      <img class="img-fluid avatar-sm avatar-item" src="assets/images/avatar/avatar-1.jpg" alt="avatar image">
                    </div>
                    <div class="flex-grow-1 text-truncate">
                      <span>David Harrison</span>
                    </div>
                  </div>
                </div>
                <div class="dropdown-item">
                  <div class="hstack gap-2 overflow-hidden">
                    <div class="flex-shrink-0">
                      <div class="avatar-item avatar-sm avatar-title border-0 text-bg-info">A</div>
                    </div>
                    <div class="flex-grow-1 text-truncate ms-2">
                      <span>Anne Richard</span>
                    </div>
                  </div>
                </div>
                <div class="dropdown-divider"></div>
                <a class="px-5 py-2 d-block text-center link-primary">
                  See all results
                  <i class="ri-arrow-right-s-line"></i>
                </a>
              </div>
            </div>
          </div>
    
          <div class="header-right hstack gap-3">
            <div class="hstack gap-0 gap-sm-1">
              <!-- Cart -->
              <div class="dropdown features-dropdown">
                <button type="button" class="btn icon-btn btn-text-primary rounded-circle position-relative" id="page-header-cart-dropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false">
                  <i class="bi bi-bag fs-2xl"></i>
                  <span class="position-absolute translate-middle badge rounded-pill p-1 min-w-20px badge text-bg-primary">5</span>
                </button>
                <div class="dropdown-menu dropdown-menu-xl dropdown-menu-end p-0" aria-labelledby="page-header-cart-dropdown">
                  <div class="card mb-0">
    
                    <!-- Cart Header -->
                    <div class="card-header">
                      <div class="d-flex justify-content-between align-items-center">
                        <h6 class="m-0 fs-5 fw-semibold">My Cart <span class="badge bg-secondary ms-1">7</span></h6>
                        <a href="#!" class="text-muted fs-6">View All</a>
                      </div>
                    </div>
    
                    <!-- Cart Items List (Scrollable) -->
                    <div class="header-cart-scrollable card-body px-0 py-2" data-simplebar>
    
                      <!-- Cart Item 1 -->
                      <div class="dropdown-item d-flex align-items-center gap-3 py-2">
                        <img src="assets/images/product/product-3.jpg" class="img-fluid rounded-3 avatar-lg" loading="lazy" alt="SoundMaster Wireless Headphones">
                        <div class="flex-grow-1 text-truncate">
                          <a href="apps-product-details.html" class="text-body fw-semibold">SoundMaster Wireless Headphones</a>
                          <div class="text-muted fs-7 text-truncate">Both fashion and functionality</div>
                        </div>
                        <span class="text-muted fs-12">3 x $219.99</span>
                      </div>
    
                      <!-- Cart Item 2 -->
                      <div class="dropdown-item d-flex align-items-center gap-3 py-2">
                        <img src="assets/images/product/product-4.jpg" class="img-fluid rounded-3 avatar-lg" loading="lazy" alt="Cocooil Body Oil">
                        <div class="flex-grow-1 text-truncate">
                          <a href="apps-product-details.html" class="text-body fw-semibold">Cocooil Body Oil</a>
                          <div class="text-muted fs- text-truncate">Natural and nourishing body oil</div>
                        </div>
                        <span class="text-muted fs-12">2 x $45.00</span>
                      </div>
    
                      <!-- Cart Item 3 -->
                      <div class="dropdown-item d-flex align-items-center gap-3 py-2">
                        <img src="assets/images/product/product-6.jpg" class="img-fluid rounded-3 avatar-lg" loading="lazy" alt="Luxury Sunglasses">
                        <div class="flex-grow-1 text-truncate">
                          <a href="apps-product-details.html" class="text-body fw-semibold">Luxury Sunglasses</a>
                          <div class="text-muted fs- text-truncate">Stylish and durable sunglasses</div>
                        </div>
                        <span class="text-muted fs-12">6 x $149.99</span>
                      </div>
    
                      <!-- Cart Item 3 -->
                      <div class="dropdown-item d-flex align-items-center gap-3 py-2">
                        <img src="assets/images/product/product-6.jpg" class="img-fluid rounded-3 avatar-lg" loading="lazy" alt="Luxury Sunglasses">
                        <div class="flex-grow-1 text-truncate">
                          <a href="apps-product-details.html" class="text-body fw-semibold">Luxury Sunglasses</a>
                          <div class="text-muted fs- text-truncate">Stylish and durable sunglasses</div>
                        </div>
                        <span class="text-muted fs-12">6 x $149.99</span>
                      </div>
    
                      <!-- Cart Item 3 -->
                      <div class="dropdown-item d-flex align-items-center gap-3 py-2">
                        <img src="assets/images/product/product-6.jpg" class="img-fluid rounded-3 avatar-lg" loading="lazy" alt="Luxury Sunglasses">
                        <div class="flex-grow-1 text-truncate">
                          <a href="apps-product-details.html" class="text-body fw-semibold">Luxury Sunglasses</a>
                          <div class="text-muted fs- text-truncate">Stylish and durable sunglasses</div>
                        </div>
                        <span class="text-muted fs-12">6 x $149.99</span>
                      </div>
                    </div>
    
                    <!-- Cart Summary -->
                    <div class="card-footer border-top">
                      <div class="d-flex justify-content-between align-items-center">
                        <h6 class="m-0 text-muted">Total</h6>
                        <div class="px-2">
                          <h5 class="m-0">$205.86</h5>
                        </div>
                      </div>
                      <a href="apps-product-checkout.html" class="btn btn-primary text-center w-100 mt-3">Proceed to Checkout</a>
                    </div>
    
                  </div>
                </div>
              </div>
    
                        <!-- Apps -->
                        <div class="dropdown features-dropdown">
                            <button type="button" class="btn icon-btn btn-text-primary rounded-circle"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="visually-hidden">SAIPS Quick Access</span>
                                <i class="ri-apps-2-line"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-lg p-0 dropdown-menu-end">
                                <div class="card shadow-none mb-0 border-0">
                                    <div class="card-header hstack gap-2">
                                        <h5 class="card-title mb-0 flex-grow-1">Quick Access</h5>
                                        <a href="dashboard.php" class="btn btn-sm btn-subtle-info flex-shrink-0">Dashboard <i class="ri-arrow-right-s-line"></i></a>
                                    </div>
                                    <div class="card-body px-3">
                                        <div class="row g-0">
                                            <div class="col"><a class="dropdown-icon-item" href="audit-log.php"><div class="avatar border-0 avatar-item bg-light mx-auto mb-2"><i class="ri-file-list-3-line text-primary"></i></div><p class="mb-1 h6 fw-medium">Audit Log</p><p class="mb-0 text-muted fs-11">All events</p></a></div>
                                            <div class="col"><a class="dropdown-icon-item" href="users.php"><div class="avatar border-0 avatar-item bg-light mx-auto mb-2"><i class="ri-group-line text-info"></i></div><p class="mb-1 h6 fw-medium">Users</p><p class="mb-0 text-muted fs-11">Manage</p></a></div>
                                            <div class="col"><a class="dropdown-icon-item" href="ips-blocked-ips.php"><div class="avatar border-0 avatar-item bg-light mx-auto mb-2"><i class="ri-forbid-2-line text-danger"></i></div><p class="mb-1 h6 fw-medium">Blocked IPs</p><p class="mb-0 text-muted fs-11">IPS</p></a></div>
                                            <div class="col"><a class="dropdown-icon-item" href="sessions-active.php"><div class="avatar border-0 avatar-item bg-light mx-auto mb-2"><i class="ri-computer-line text-success"></i></div><p class="mb-1 h6 fw-medium">Sessions</p><p class="mb-0 text-muted fs-11">Active</p></a></div>
                                            <div class="col"><a class="dropdown-icon-item" href="incidents-list.php"><div class="avatar border-0 avatar-item bg-light mx-auto mb-2"><i class="ri-alarm-warning-line text-warning"></i></div><p class="mb-1 h6 fw-medium">Incidents</p><p class="mb-0 text-muted fs-11">Respond</p></a></div>
                                            <div class="col"><a class="dropdown-icon-item" href="settings-compliance.php"><div class="avatar border-0 avatar-item bg-light mx-auto mb-2"><i class="ri-shield-check-line text-success"></i></div><p class="mb-1 h6 fw-medium">Compliance</p><p class="mb-0 text-muted fs-11">ISO/GDPR</p></a></div>
                                            <div class="col"><a class="dropdown-icon-item" href="cap512-demo.php"><div class="avatar border-0 avatar-item bg-light mx-auto mb-2"><i class="ri-code-box-line text-primary"></i></div><p class="mb-1 h6 fw-medium">CAP512</p><p class="mb-0 text-muted fs-11">Demo</p></a></div>
                                            <div class="col"><a class="dropdown-icon-item" href="settings-policy.php"><div class="avatar border-0 avatar-item bg-light mx-auto mb-2"><i class="ri-lock-password-line text-secondary"></i></div><p class="mb-1 h6 fw-medium">Policy</p><p class="mb-0 text-muted fs-11">Settings</p></a></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
    
              <!-- Language -->
              <div class="dropdown features-dropdown" id="language-dropdown">
                <a href="#!" class="btn icon-btn btn-text-primary rounded-circle" data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="avatar-item avatar-xs">
                    <img class="img-fluid avatar-xs" src="assets/images/flags/us.svg" loading="lazy" alt="avatar image">
                  </div>
                </a>
    
                <div class="dropdown-menu header-language-scrollable dropdown-menu-end" data-simplebar>
    
                  <a href="#!" class="dropdown-item py-2">
                    <img src="assets/images/flags/us.svg" alt="en" loading="lazy" class="me-2 rounded h-20px w-20px img-fluid object-fit-cover">
                    <span class="align-middle">English</span>
                  </a>
    
                  <a href="#!" class="dropdown-item">
                    <img src="assets/images/flags/es.svg" alt="es" loading="lazy" class="me-2 rounded h-20px w-20px img-fluid object-fit-cover">
                    <span class="align-middle">Spanish</span>
                  </a>
    
                  <a href="#!" class="dropdown-item">
                    <img src="assets/images/flags/fr.svg" alt="fr" loading="lazy" class="me-2 rounded h-20px w-20px img-fluid object-fit-cover">
                    <span class="align-middle">French</span>
                  </a>
    
                  <a href="#!" class="dropdown-item">
                    <img src="assets/images/flags/ru.svg" alt="ru" loading="lazy" class="me-2 rounded h-20px w-20px img-fluid object-fit-cover">
                    <span class="align-middle">Russian</span>
                  </a>
    
                  <a href="#!" class="dropdown-item">
                    <img src="assets/images/flags/de.svg" alt="de" loading="lazy" class="me-2 rounded h-20px w-20px img-fluid object-fit-cover">
                    <span class="align-middle">German</span>
                  </a>
    
                  <a href="#!" class="dropdown-item">
                    <img src="assets/images/flags/it.svg" alt="it" loading="lazy" class="me-2 rounded h-20px w-20px img-fluid object-fit-cover">
                    <span class="align-middle">Italian</span>
                  </a>
    
                  <a href="#!" class="dropdown-item">
                    <img src="assets/images/flags/zh.svg" alt="zh" loading="lazy" class="me-2 rounded h-20px w-20px img-fluid object-fit-cover">
                    <span class="align-middle">Chinese</span>
                  </a>
    
                  <a href="#!" class="dropdown-item">
                    <img src="assets/images/flags/ar.svg" alt="ar" loading="lazy" class="me-2 rounded h-20px w-20px img-fluid object-fit-cover">
                    <span class="align-middle">Arabic</span>
                  </a>
    
                </div>
              </div>
    
              <!-- Theme -->
              <div class="dropdown features-dropdown d-none d-sm-block">
                <button type="button" class="btn icon-btn btn-text-primary rounded-circle" data-bs-toggle="dropdown" aria-expanded="false">
                  <span class="visually-hidden">Light or Dark Mode Switch</span>
                  <i class="ri-sun-line fs-20"></i>
                </button>
    
                <div class="dropdown-menu dropdown-menu-end header-language-scrollable" data-simplebar>
    
                  <div class="dropdown-item cursor-pointer" id="light-theme">
                    <span class="hstack gap-2 align-middle"><i class="ri-sun-line"></i>Light</span>
                  </div>
                  <div class="dropdown-item cursor-pointer" id="dark-theme">
                    <span class="hstack gap-2 align-middle"><i class="ri-moon-clear-line"></i>Dark</span>
                  </div>
                  <div class="dropdown-item cursor-pointer" id="system-theme">
                    <span class="hstack gap-2 align-middle"><i class="ri-computer-line"></i>System</span>
                  </div>
    
                </div>
              </div>
    
                        <!-- Notification -->
                        <div class="dropdown features-dropdown">
                            <button type="button" class="btn icon-btn btn-text-primary rounded-circle position-relative"
                                id="page-header-notifications-dropdown" data-bs-toggle="dropdown"
                                data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false">
                                <i class="ri-notification-2-line fs-20"></i>
                                <span class="position-absolute translate-middle badge rounded-pill p-1 min-w-20px badge text-bg-danger" id="notif-count">3</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-lg p-0 dropdown-menu-end" aria-labelledby="page-header-notifications-dropdown">
                                <div class="card shadow-none mb-0 border-0">
                                    <div class="card-header hstack gap-2">
                                        <h5 class="card-title mb-0 flex-grow-1 hstack gap-2">
                                            <i class="ri-alarm-warning-line text-danger"></i>Security Alerts
                                        </h5>
                                        <a href="incidents-list.php" class="btn btn-sm btn-subtle-danger">View all</a>
                                    </div>
                                    <div class="card-body p-0">
                                        <ul class="list-group list-group-flush notification-list">
                                            <li class="list-group-item list-group-item-action border-start-0 border-end-0">
                                                <div class="d-flex gap-3 align-items-start">
                                                    <div class="avatar-item avatar avatar-title bg-danger-subtle text-danger rounded-circle flex-shrink-0"><i class="ri-error-warning-line"></i></div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 small fw-semibold">SEV-1: Brute-Force Detected</h6>
                                                        <small class="mb-0 d-block text-body">185.220.101.47 — 47 failures in 5min</small>
                                                        <small class="text-muted">2 min ago · <a href="ips-brute-force.php" class="text-danger">Investigate</a></small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="list-group-item list-group-item-action border-start-0 border-end-0">
                                                <div class="d-flex gap-3 align-items-start">
                                                    <div class="avatar-item avatar avatar-title bg-warning-subtle text-warning rounded-circle flex-shrink-0"><i class="ri-lock-line"></i></div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 small fw-semibold">Account Locked</h6>
                                                        <small class="mb-0 d-block text-body">james.harris@acme.com — 10 failures</small>
                                                        <small class="text-muted">14 min ago · <a href="users.php" class="text-warning">Unlock</a></small>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="list-group-item list-group-item-action border-start-0 border-end-0">
                                                <div class="d-flex gap-3 align-items-start">
                                                    <div class="avatar-item avatar avatar-title bg-primary-subtle text-primary rounded-circle flex-shrink-0"><i class="ri-global-line"></i></div>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1 small fw-semibold">Geo-Block Triggered</h6>
                                                        <small class="mb-0 d-block text-body">Login attempt from KP (denied)</small>
                                                        <small class="text-muted">1 hr ago · <a href="ips-geo-block.html" class="text-primary">Review</a></small>
                                                    </div>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="card-footer text-center">
                                        <a href="audit-log.php" class="text-muted fs-12">View full audit log <i class="ri-arrow-right-s-line"></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
    
              <!-- Fullscreen -->
              <button type="button" id="fullscreen-button" class="btn icon-btn btn-text-primary rounded-circle custom-toggle d-none d-sm-block" aria-pressed="false">
                <span class="visually-hidden">Toggle Fullscreen</span>
                <span class="icon-on">
                  <i class="ri-fullscreen-exit-line fs-16"></i>
                </span>
                <span class="icon-off">
                  <i class="ri-fullscreen-line fs-16"></i>
                </span>
              </button>
            </div>
    
            <!-- Profile Section -->
                        <!-- Profile Dropdown -->
                        <div class="dropdown profile-dropdown ms-2">
                            <a href="#!" class="d-flex align-items-center gap-2 nav-link dropdown-toggle"
                               id="profile-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="avatar-item avatar rounded-circle bg-primary-subtle text-primary fw-bold">SJ</div>
                                <div class="d-none d-xl-block">
                                    <h6 class="mb-0 fs-13 fw-semibold">Sophia Johnson</h6>
                                    <span class="text-muted fs-11">Superadmin</span>
                                </div>
                                <i class="ri-arrow-down-s-line text-muted d-none d-xl-block"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end shadow-lg" aria-labelledby="profile-dropdown">
                                <div class="px-3 py-2 border-bottom">
                                    <h6 class="mb-0 fw-semibold">Sophia Johnson</h6>
                                    <small class="text-muted">sophia.johnson@acme.com</small>
                                </div>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="dashboard.php">
                                    <i class="ri-shield-line text-primary"></i> Security Dashboard
                                </a>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="users.php">
                                    <i class="ri-group-line text-info"></i> Manage Users
                                </a>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="audit-log.php">
                                    <i class="ri-file-list-3-line text-secondary"></i> My Audit Log
                                </a>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="settings-mfa.html">
                                    <i class="ri-smartphone-line text-success"></i> MFA Settings
                                </a>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="auth-lockscreen.html">
                                    <i class="ri-lock-line text-warning"></i> Lock Session
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item d-flex align-items-center gap-2 py-2 text-danger" href="auth-signin.html">
                                    <i class="ri-logout-box-r-line"></i> Sign Out
                                </a>
                            </div>
                        </div>
          </div>
        </div>
      </div>
    
    </header>
    <!-- END HEADER -->
    <!-- START SIDEBAR -->
    <aside class="app-sidebar">
        <!-- START BRAND LOGO -->
        <div class="app-sidebar-logo px-6 justify-content-center align-items-center">
            <a href="index.html">
                <img height="70" class="app-sidebar-logo-default" alt="Logo" src="assets/images/light-logo.png">
                <img height="40" class="app-sidebar-logo-minimize" alt="Logo" src="assets/images/Favicon.png">
            </a>
        </div>
        <!-- END BRAND LOGO -->
        <nav class="app-sidebar-menu nav nav-pills flex-column fs-6" id="sidebarMenu" aria-label="Main navigation">
            <ul class="main-menu" id="all-menu-items" role="menu">
                <li class="menu-title" role="presentation">Overview</li>
                <li class="slide"><a href="dashboard.php" class="side-menu__item" role="menuitem"><span class="side_menu_icon"><i class="ri-shield-line"></i></span><span class="side-menu__label">Security Dashboard</span></a></li>
                <li class="menu-title" role="presentation">Authentication</li>
                <li class="slide has-sub"><a href="#!" class="side-menu__item" role="menuitem"><span class="side_menu_icon"><i class="ri-login-box-line"></i></span><span class="side-menu__label">Auth Gateway</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="auth-signin.html" class="side-menu__item">Sign In</a></li>
                        <li class="slide"><a href="auth-two-step-verify.html" class="side-menu__item">MFA Verify</a></li>
                        <li class="slide"><a href="auth-lockscreen.html" class="side-menu__item">Session Lock</a></li>
                        <li class="slide"><a href="auth-reset-password.html" class="side-menu__item">Reset Password</a></li>
                        <li class="slide"><a href="auth-create-password.html" class="side-menu__item">Create Password</a></li>
                    </ul>
                </li>
                <li class="menu-title" role="presentation">Intrusion Prevention</li>
                <li class="slide has-sub"><a href="#!" class="side-menu__item" role="menuitem"><span class="side_menu_icon"><i class="ri-radar-line"></i></span><span class="side-menu__label">IPS Monitor</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="ips-blocked-ips.php" class="side-menu__item">Blocked IPs</a></li>
                        <li class="slide"><a href="ips-brute-force.php" class="side-menu__item">Brute-Force Alerts</a></li>
                        <li class="slide"><a href="ips-geo-block.html" class="side-menu__item">Geo-Block Rules</a></li>
                        <li class="slide"><a href="ips-rate-limits.html" class="side-menu__item">Rate Limit Config</a></li>
                    </ul>
                </li>
                <li class="menu-title" role="presentation">Sessions</li>
                <li class="slide has-sub"><a href="#!" class="side-menu__item" role="menuitem"><span class="side_menu_icon"><i class="ri-computer-line"></i></span><span class="side-menu__label">Session Management</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="sessions-active.php" class="side-menu__item">Active Sessions</a></li>
                        <li class="slide"><a href="sessions-revoke.html" class="side-menu__item">Revoke Sessions</a></li>
                    </ul>
                </li>
                <li class="menu-title" role="presentation">Audit &amp; Monitoring</li>
                <li class="slide has-sub"><a href="#!" class="side-menu__item" role="menuitem"><span class="side_menu_icon"><i class="ri-file-list-3-line"></i></span><span class="side-menu__label">Audit Log</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="audit-log.php" class="side-menu__item">Full Audit Log</a></li>
                        <li class="slide"><a href="audit-log.php?category=AUTH" class="side-menu__item">Auth Events</a></li>
                        <li class="slide"><a href="audit-log.php?category=IPS" class="side-menu__item">IPS Events</a></li>
                        <li class="slide"><a href="audit-log.php?category=ADM" class="side-menu__item">Admin Events</a></li>
                    </ul>
                </li>
                <li class="menu-title" role="presentation">Incidents</li>
                <li class="slide has-sub"><a href="#!" class="side-menu__item" role="menuitem"><span class="side_menu_icon"><i class="ri-alarm-warning-line"></i></span><span class="side-menu__label">Incident Response</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="incidents-list.php" class="side-menu__item">All Incidents</a></li>
                        <li class="slide"><a href="incidents-report.php" class="side-menu__item">File Incident Report</a></li>
                    </ul>
                </li>
                <li class="menu-title" role="presentation">Administration</li>
                <li class="slide has-sub"><a href="#!" class="side-menu__item" role="menuitem"><span class="side_menu_icon"><i class="ri-group-line"></i></span><span class="side-menu__label">User Management</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="users.php" class="side-menu__item">All Users</a></li>
                        <li class="slide"><a href="users-mfa.php" class="side-menu__item">MFA Status</a></li>
                        <li class="slide"><a href="users-roles.html" class="side-menu__item">Roles &amp; Permissions</a></li>
                    </ul>
                </li>
                <li class="slide has-sub"><a href="#!" class="side-menu__item" role="menuitem"><span class="side_menu_icon"><i class="ri-settings-3-line"></i></span><span class="side-menu__label">System Settings</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="settings-policy.php" class="side-menu__item">Password Policy</a></li>
                        <li class="slide"><a href="settings-mfa.html" class="side-menu__item">MFA Config</a></li>
                        <li class="slide"><a href="settings-alert-rules.html" class="side-menu__item">Alert Rules</a></li>
                        <li class="slide"><a href="settings-compliance.php" class="side-menu__item">Compliance Checklist</a></li>
                    </ul>
                </li>
                <li class="menu-title" role="presentation">System</li>
                <li class="slide has-sub"><a href="#!" class="side-menu__item" role="menuitem"><span class="side_menu_icon"><i class="ri-error-warning-line"></i></span><span class="side-menu__label">Error Pages</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="auth-401.html" class="side-menu__item">401 Unauthorized</a></li>
                        <li class="slide"><a href="auth-404.html" class="side-menu__item">404 Not Found</a></li>
                        <li class="slide"><a href="auth-500.html" class="side-menu__item">500 Server Error</a></li>
                        <li class="slide"><a href="under-maintenance.html" class="side-menu__item">Maintenance</a></li>
                    </ul>
                </li>
            </ul>
            </nav>
    </aside>
    <!-- END SIDEBAR -->
    <div class="horizontal-overlay"></div>
    
    <!-- START SMALL SCREEN SIDEBAR -->
    <div class="offcanvas offcanvas-md offcanvas-start small-screen-sidebar" data-bs-scroll="true" tabindex="-1"
        id="smallScreenSidebar" aria-labelledby="smallScreenSidebarLabel">
        <div class="offcanvas-header hstack border-bottom">
            <div class="app-sidebar-logo">
                <a href="dashboard.php">
                    <img height="70" class="app-sidebar-logo-default h-25px" alt="Logo" src="assets/images/light-logo.png">
                    <img height="40" class="app-sidebar-logo-minimize" alt="Logo" src="assets/images/Favicon.png">
                </a>
            </div>
            <button type="button" class="btn-close bg-transparent" data-bs-dismiss="offcanvas" aria-label="Close">
                <i class="ri-close-line"></i>
            </button>
        </div>
        <div class="offcanvas-body p-0">
            <aside class="app-sidebar">
                <nav class="app-sidebar-menu nav nav-pills flex-column fs-6" aria-label="Main navigation">
                    <ul class="main-menu" role="menu">
                        <li class="menu-title" role="presentation">Overview</li>
                        <li class="slide"><a href="dashboard.php" class="side-menu__item"><span class="side_menu_icon"><i class="ri-shield-line"></i></span><span class="side-menu__label">Security Dashboard</span></a></li>
                        <li class="menu-title" role="presentation">Authentication</li>
                        <li class="slide has-sub"><a href="#!" class="side-menu__item"><span class="side_menu_icon"><i class="ri-login-box-line"></i></span><span class="side-menu__label">Auth Gateway</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="auth-signin.html" class="side-menu__item">Sign In</a></li>
                                <li class="slide"><a href="auth-two-step-verify.html" class="side-menu__item">MFA Verify</a></li>
                                <li class="slide"><a href="auth-lockscreen.html" class="side-menu__item">Session Lock</a></li>
                                <li class="slide"><a href="auth-reset-password.html" class="side-menu__item">Reset Password</a></li>
                                <li class="slide"><a href="auth-create-password.html" class="side-menu__item">Create Password</a></li>
                            </ul>
                        </li>
                        <li class="menu-title" role="presentation">Intrusion Prevention</li>
                        <li class="slide has-sub"><a href="#!" class="side-menu__item"><span class="side_menu_icon"><i class="ri-radar-line"></i></span><span class="side-menu__label">IPS Monitor</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="ips-blocked-ips.php" class="side-menu__item">Blocked IPs</a></li>
                                <li class="slide"><a href="ips-brute-force.php" class="side-menu__item">Brute-Force Alerts</a></li>
                                <li class="slide"><a href="ips-geo-block.html" class="side-menu__item">Geo-Block Rules</a></li>
                                <li class="slide"><a href="ips-rate-limits.html" class="side-menu__item">Rate Limit Config</a></li>
                            </ul>
                        </li>
                        <li class="menu-title" role="presentation">Sessions</li>
                        <li class="slide has-sub"><a href="#!" class="side-menu__item"><span class="side_menu_icon"><i class="ri-computer-line"></i></span><span class="side-menu__label">Session Management</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="sessions-active.php" class="side-menu__item">Active Sessions</a></li>
                                <li class="slide"><a href="sessions-revoke.html" class="side-menu__item">Revoke Sessions</a></li>
                            </ul>
                        </li>
                        <li class="menu-title" role="presentation">Audit &amp; Monitoring</li>
                        <li class="slide has-sub"><a href="#!" class="side-menu__item"><span class="side_menu_icon"><i class="ri-file-list-3-line"></i></span><span class="side-menu__label">Audit Log</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="audit-log.php" class="side-menu__item">Full Audit Log</a></li>
                                <li class="slide"><a href="audit-log.php?category=AUTH" class="side-menu__item">Auth Events</a></li>
                                <li class="slide"><a href="audit-log.php?category=IPS" class="side-menu__item">IPS Events</a></li>
                                <li class="slide"><a href="audit-log.php?category=ADM" class="side-menu__item">Admin Events</a></li>
                            </ul>
                        </li>
                        <li class="menu-title" role="presentation">Incidents</li>
                        <li class="slide has-sub"><a href="#!" class="side-menu__item"><span class="side_menu_icon"><i class="ri-alarm-warning-line"></i></span><span class="side-menu__label">Incident Response</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="incidents-list.php" class="side-menu__item">All Incidents</a></li>
                                <li class="slide"><a href="incidents-report.php" class="side-menu__item">File Incident Report</a></li>
                            </ul>
                        </li>
                        <li class="menu-title" role="presentation">Administration</li>
                        <li class="slide has-sub"><a href="#!" class="side-menu__item"><span class="side_menu_icon"><i class="ri-group-line"></i></span><span class="side-menu__label">User Management</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="users.php" class="side-menu__item">All Users</a></li>
                                <li class="slide"><a href="users-mfa.php" class="side-menu__item">MFA Status</a></li>
                                <li class="slide"><a href="users-roles.html" class="side-menu__item">Roles &amp; Permissions</a></li>
                            </ul>
                        </li>
                        <li class="slide has-sub"><a href="#!" class="side-menu__item"><span class="side_menu_icon"><i class="ri-settings-3-line"></i></span><span class="side-menu__label">System Settings</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="settings-policy.php" class="side-menu__item">Password Policy</a></li>
                                <li class="slide"><a href="settings-mfa.html" class="side-menu__item">MFA Config</a></li>
                                <li class="slide"><a href="settings-alert-rules.html" class="side-menu__item">Alert Rules</a></li>
                                <li class="slide"><a href="settings-compliance.php" class="side-menu__item">Compliance Checklist</a></li>
                            </ul>
                        </li>
                        <li class="menu-title" role="presentation">System</li>
                        <li class="slide has-sub"><a href="#!" class="side-menu__item"><span class="side_menu_icon"><i class="ri-error-warning-line"></i></span><span class="side-menu__label">Error Pages</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="auth-401.html" class="side-menu__item">401 Unauthorized</a></li>
                                <li class="slide"><a href="auth-404.html" class="side-menu__item">404 Not Found</a></li>
                                <li class="slide"><a href="auth-500.html" class="side-menu__item">500 Server Error</a></li>
                                <li class="slide"><a href="under-maintenance.html" class="side-menu__item">Maintenance</a></li>
                            </ul>
                        </li>
                    </ul>
                </nav>
            </aside>
        </div>
    </div>
    <!-- END SMALL SCREEN SIDEBAR -->
    <!-- START PRELOADER -->
    <div class="align-items-center justify-content-center" id="preloader">
      <div class="spinner-border text-primary avatar-sm" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
    </div>
    <!-- END PRELOADER -->

    <!-- START -->
    <div class="account-pages">
        <img src="assets/images/auth/auth_bg.jpg" alt="auth_bg" class="auth-bg light">
        <img src="assets/images/auth/auth_bg_dark.jpg" alt="auth_bg_dark" class="auth-bg dark">
        <div class="container">
            <div class="justify-content-center row gy-0">

                <div class="col-lg-6 auth-banners">
                    <div class="bg-login card card-body m-0 h-100 border-0">
                        <img src="assets/images/auth/bg-img-2.jpg" class="img-fluid auth-banner" alt="auth-banner">
                        <div class="auth-contain">
                            <div id="carouselExampleIndicators" class="carousel slide" data-bs-ride="carousel">
                                <div class="carousel-indicators">
                                    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
                                    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="1" aria-label="Slide 2"></button>
                                    <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="2" aria-label="Slide 3"></button>
                                </div>
                                <div class="carousel-inner">
                                    <div class="carousel-item active">
                                        <div class="text-center text-white my-4 p-4">
                                            <i class="ri-lock-password-line fs-1 mb-3 d-block opacity-75"></i>
                                            <h3 class="text-white">Credential Requirements (SRS §2.2)</h3>
                                            <p class="mt-3">Min 12 / max 128 characters. Must use 3 of 4 classes: uppercase, lowercase, digit, special. Cannot resemble your username or email address.</p>
                                        </div>
                                    </div>
                                    <div class="carousel-item">
                                        <div class="text-center text-white my-4 p-4">
                                            <i class="ri-history-line fs-1 mb-3 d-block opacity-75"></i>
                                            <h3 class="text-white">Password History Enforced</h3>
                                            <p class="mt-3">Your new password cannot match any of your last 12 passwords. Standard accounts expire every 180 days. Privileged accounts every 90 days.</p>
                                        </div>
                                    </div>
                                    <div class="carousel-item">
                                        <div class="text-center text-white my-4 p-4">
                                            <i class="ri-database-2-line fs-1 mb-3 d-block opacity-75"></i>
                                            <h3 class="text-white">Breached Password Check</h3>
                                            <p class="mt-3">All new passwords are checked against the HIBP database and a custom dictionary of common credentials. Matching passwords are rejected.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="auth-box card card-body m-0 h-100 border-0 justify-content-center">
                        <div class="mb-5 text-center">
                            <div class="mb-3"><span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 fs-12"><i class="ri-lock-password-line me-1"></i>Set New Password · Event AUTH-005</span></div>
                            <h4 class="fw-medium">Create New Password</h4>
                            <p class="text-muted mb-0">Your new password must meet the SAIPS credential requirements in §2.2. It will be bcrypt hashed (cost 12) at rest.</p>
                        </div>
                        <form class="form-custom mt-10">

                            <div class="mb-5">
                                <label class="form-label" for="LoginPassword">New Password<span class="text-danger ms-1">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent"><i class="ri-lock-line text-muted"></i></span>
                                    <input type="password" id="LoginPassword" class="form-control" name="password" placeholder="Min. 12 characters" data-visible="false" autocomplete="new-password">
                                    <a class="input-group-text bg-transparent toggle-password" href="javascript:;" data-target="password">
                                        <i class="ri-eye-off-line text-muted toggle-icon"></i>
                                    </a>
                                </div>
                                <div class="d-flex align-items-center mb-2 mt-2">
                                    <div class="flex-grow-1 bg-light rounded h-4px me-2"></div>
                                    <div class="flex-grow-1 bg-light rounded h-4px me-2"></div>
                                    <div class="flex-grow-1 bg-light rounded h-4px me-2"></div>
                                    <div class="flex-grow-1 bg-light rounded h-4px"></div>
                                </div>
                                <div class="d-flex flex-wrap gap-2 fs-11 text-muted mt-1">
                                    <span class="badge bg-light text-muted border"><i class="ri-check-line me-1"></i>12–128 chars</span>
                                    <span class="badge bg-light text-muted border"><i class="ri-check-line me-1"></i>3 of 4 classes</span>
                                    <span class="badge bg-light text-muted border"><i class="ri-check-line me-1"></i>Not in last 12</span>
                                    <span class="badge bg-light text-muted border"><i class="ri-check-line me-1"></i>Not in HIBP</span>
                                </div>
                            </div>

                            <div class="mb-5">
                                <label class="form-label" for="confirmPassword">Confirm Password<span class="text-danger ms-1">*</span></label>
                                <div class="input-group">
                                    <input type="password" id="confirmPassword" class="form-control" name="password" placeholder="Confirm your password" data-visible="false">
                                    <a class="input-group-text bg-transparent toggle-password" href="javascript:;" data-target="password">
                                        <i class="ri-eye-off-line text-muted toggle-icon"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="form-check form-check-sm d-flex align-items-center gap-2 mb-5">
                                <input class="form-check-input" type="checkbox" value="remember-me" id="remember-me">
                                <label class="form-check-label" for="remember-me">
                                    Remember me
                                </label>
                            </div>

                            <div id="pw-alert" class="alert d-none mb-3" role="alert"></div>
                            <button type="button" id="pw-btn" class="btn btn-primary rounded-2 w-100 btn-loader" onclick="handlePasswordReset(this)">
                                <span class="indicator-label">
                                    Reset Password
                                </span>
                                <span class="indicator-progress flex gap-2 justify-content-center w-100">
                                    <span>Please Wait...</span>
                                    <i class="ri-loader-2-fill"></i>
                                </span>
                            </button>

                            <p class="mb-0 mt-10 text-muted text-center">
                                I Remember!, Let me try once...
                                <a href="auth-signin.html" class="text-primary fw-medium text-decoraton-underline ms-1">
                                    Sign In
                                </a>
                            </p>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script src="assets/js/sidebar.js"></script>
    <script src="assets/libs/choices.js/public/assets/scripts/choices.min.js"></script>
    <script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="assets/libs/simplebar/simplebar.min.js"></script>
    <script src="assets/js/pages/scroll-top.init.js"></script>
    <script src="assets/libs/sweetalert2/sweetalert2.min.js"></script>

    <!-- App js -->
    <script type="module" src="assets/js/app.js"></script>


<script>
(function() {
    // Read token from URL ?token=...
    const params = new URLSearchParams(window.location.search);
    window.RESET_TOKEN = params.get('token') || '';
    if (!window.RESET_TOKEN) {
        const el = document.getElementById('pw-alert');
        if (el) {
            el.className = 'alert alert-warning mb-3';
            el.textContent = 'No reset token found. Please request a new password reset link.';
            el.classList.remove('d-none');
        }
        const btn = document.getElementById('pw-btn');
        if (btn) btn.disabled = true;
    }
})();

async function handlePasswordReset(btn) {
    const newPass     = (document.getElementById('LoginPassword')?.value  || '');
    const confirmPass = (document.getElementById('confirmPassword')?.value || '');
    const alertBox    = document.getElementById('pw-alert');
    const token       = window.RESET_TOKEN;

    function showAlert(msg, type) {
        if (!alertBox) { alert(msg); return; }
        alertBox.className = 'alert alert-' + type + ' mb-3';
        alertBox.textContent = msg;
        alertBox.classList.remove('d-none');
    }

    if (!newPass || !confirmPass) {
        showAlert('Both password fields are required.', 'warning');
        return;
    }
    if (newPass !== confirmPass) {
        showAlert('Passwords do not match.', 'warning');
        return;
    }
    if (newPass.length < 12) {
        showAlert('Password must be at least 12 characters.', 'warning');
        return;
    }
    if (!token) {
        showAlert('Invalid or missing reset token. Please request a new reset link.', 'danger');
        return;
    }

    btn.disabled = true;
    btn.querySelector('.indicator-label')?.classList.add('d-none');
    btn.querySelector('.indicator-progress')?.classList.remove('d-none');
    if (alertBox) alertBox.classList.add('d-none');

    try {
        const res  = await fetch('backend/api/auth/password-change.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                reset_token:          token,
                new_password:         newPass,
                new_password_confirm: confirmPass,
            }),
        });
        const data = await res.json();

        if (res.ok && data.status === 'success') {
            showAlert('Password changed successfully. Redirecting to sign-in...', 'success');
            setTimeout(() => { window.location.href = 'login.php'; }, 2000);
        } else {
            const errs = data.errors ? data.errors.join(' ') : (data.message || 'Password reset failed.');
            showAlert(errs, 'danger');
            btn.disabled = false;
            btn.querySelector('.indicator-label')?.classList.remove('d-none');
            btn.querySelector('.indicator-progress')?.classList.add('d-none');
        }
    } catch (err) {
        showAlert('Network error. Please try again.', 'danger');
        btn.disabled = false;
        btn.querySelector('.indicator-label')?.classList.remove('d-none');
        btn.querySelector('.indicator-progress')?.classList.add('d-none');
    }
}
</script>
</body>


<!-- auth-create-password.html Ownuh, 16:38 GMT -->
</html>
