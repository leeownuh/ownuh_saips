<?php
/**
 * Ownuh SAIPS — Header Partial
 * CAP512 Unit II: Embedding PHP in web pages
 */
if (!isset($user)) $user = [];
?>
<!-- START HEADER -->
    <header class="app-header">
        <div class="container-fluid">
            <div class="nav-header">

                <div class="header-left hstack gap-3">
                    <!-- HORIZONTAL BRAND LOGO -->
                    <div class="app-sidebar-logo app-horizontal-logo justify-content-center align-items-center">
                        <a href="dashboard.php">
                            <img height="70" class="app-sidebar-logo-default" alt="Logo" loading="lazy"
                                src="assets/images/light-logo.png">
                            <img height="40" class="app-sidebar-logo-minimize" alt="Logo" loading="lazy"
                                src="assets/images/Favicon.png">
                        </a>
                    </div>

                    <!-- Sidebar Toggle Btn -->
                    <button type="button" class="btn btn-light-light icon-btn sidebar-toggle d-none d-md-block"
                        aria-expanded="false" aria-controls="main-menu">
                        <span class="visually-hidden">Toggle sidebar</span>
                        <i class="ri-menu-2-fill"></i>
                    </button>

                    <!-- Sidebar Toggle for Mobile -->
                    <button class="btn btn-light-light icon-btn d-md-none small-screen-toggle"
                        id="smallScreenSidebarLabel" type="button" data-bs-toggle="offcanvas"
                        data-bs-target="#smallScreenSidebar" aria-controls="smallScreenSidebar">
                        <span class="visually-hidden">Sidebar toggle for mobile</span>
                        <i class="ri-arrow-right-fill"></i>
                    </button>

                    <!-- Sidebar Toggle for Horizontal Menu -->
                    <button class="btn btn-light-light icon-btn d-lg-none small-screen-horizontal-toggle" type="button"
                        ria-expanded="false" aria-controls="main-menu">
                        <span class="visually-hidden">Sidebar toggle for horizontal</span>
                        <i class="ri-arrow-right-fill"></i>
                    </button>

                    <!-- Search Dropdown -->
                    <div class="dropdown features-dropdown header-search-dropdown">

                        <!-- Search Input for Desktop -->
                        <form class="d-none d-sm-block header-search" id="globalSearchForm" autocomplete="off">
                            <div class="form-icon">
                                <input type="search" class="form-control form-control-icon" id="globalSearchInput"
                                    placeholder="Search pages, incidents, users..." aria-label="Search in Ownuh">
                                <i class="ri-search-2-line text-muted"></i>
                            </div>
                        </form>

                        <!-- Search Button for Mobile -->
                        <button type="button" class="btn btn-light-light icon-btn d-sm-none" id="globalSearchMobileButton"
                            aria-expanded="false">
                            <span class="visually-hidden">Search</span>
                            <i class="ri-search-2-line text-muted"></i>
                        </button>

                        <div class="dropdown-menu p-0 overflow-hidden" id="globalSearchMenu" style="width:min(32rem, calc(100vw - 2rem));">
                            <div class="p-3 border-bottom d-sm-none">
                                <div class="form-icon">
                                    <input type="search" class="form-control form-control-icon" id="globalSearchInputMobile"
                                        placeholder="Search pages, incidents, users..." aria-label="Search in Ownuh mobile">
                                    <i class="ri-search-2-line text-muted"></i>
                                </div>
                            </div>
                            <div class="p-2" id="globalSearchResults">
                                <div class="px-3 py-2 text-muted fs-12">Start typing to search the main pages.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="header-right hstack gap-3">
                    <div class="hstack gap-0 gap-sm-1">
                        <!-- Cart -->
                        

                        <!-- Apps -->
                        

                        

                        <!-- Theme -->
                        <div class="dropdown features-dropdown d-none d-sm-block">
                            <button type="button" class="btn icon-btn btn-text-primary rounded-circle"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="visually-hidden">Light or Dark Mode Switch</span>
                                <i class="ri-sun-line fs-20"></i>
                            </button>

                            <div class="dropdown-menu dropdown-menu-end header-language-scrollable" data-simplebar>

                                <div class="dropdown-item cursor-pointer" id="light-theme">
                                    <span class="hstack gap-2 align-middle"><i class="ri-sun-line"></i>Light</span>
                                </div>
                                <div class="dropdown-item cursor-pointer" id="dark-theme">
                                    <span class="hstack gap-2 align-middle"><i
                                            class="ri-moon-clear-line"></i>Dark</span>
                                </div>
                                <div class="dropdown-item cursor-pointer" id="system-theme">
                                    <span class="hstack gap-2 align-middle"><i
                                            class="ri-computer-line"></i>System</span>
                                </div>

                            </div>
                        </div>
                        <!-- Fullscreen -->
                        <button type="button" id="fullscreen-button"
                            class="btn icon-btn btn-text-primary rounded-circle custom-toggle d-none d-sm-block"
                            aria-pressed="false">
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
                    <div class="d-flex align-items-center ms-2">
                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle"
                               id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="avatar avatar-sm avatar-title rounded-circle bg-primary text-white fw-bold"
                                     style="width:36px;height:36px;font-size:14px;">
                                    <?php
                                        $dn = $user['email'] ?? 'U';
                                        $parts = explode(' ', trim($user['display_name'] ?? $dn));
                                        echo strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
                                    ?>
                                </div>
                                <div class="d-none d-md-block text-start lh-sm">
                                    <span class="d-block fw-semibold fs-13"><?= htmlspecialchars($user['display_name'] ?? $user['email'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></span>
                                    <small class="text-muted fs-11"><?= htmlspecialchars(ucfirst($user['role'] ?? 'user'), ENT_QUOTES, 'UTF-8') ?></small>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm mt-1" aria-labelledby="profileDropdown"
                                style="min-width:200px;">
                                <li class="px-3 py-2 border-bottom">
                                    <p class="mb-0 fw-semibold fs-13"><?= htmlspecialchars($user['display_name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></p>
                                    <small class="text-muted"><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></small>
                                </li>
                                <li><a class="dropdown-item py-2" href="users.php"><i class="ri-user-line me-2 text-muted"></i>My Profile</a></li>
                                <li><a class="dropdown-item py-2" href="settings-mfa.php"><i class="ri-shield-keyhole-line me-2 text-muted"></i>MFA Settings</a></li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li>
                                    <a class="dropdown-item py-2 text-danger" href="logout.php"
                                       onclick="return confirm('Sign out of Ownuh SAIPS?')">
                                        <i class="ri-logout-box-line me-2"></i>Sign Out
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </header>
    <!-- END HEADER -->
    <script src="assets/js/theme-fullscreen-fix.js"></script>
    <script src="assets/js/header-search.js"></script>
    <link href="assets/css/theme-fixes.css" rel="stylesheet">
