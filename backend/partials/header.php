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
                            <img height="100" class="app-sidebar-logo-default" alt="Logo" loading="lazy"
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
                    <div class="dropdown features-dropdown">

                        <!-- Search Input for Desktop -->
                        <form class="d-none d-sm-block header-search" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="form-icon">
                                <input type="search" class="form-control form-control-icon" id="firstNameLayout4"
                                    placeholder="Search in Ownuh" required>
                                <i class="ri-search-2-line text-muted"></i>
                            </div>
                        </form>

                        <!-- Search Button for Mobile -->
                        <button type="button" class="btn btn-light-light icon-btn d-sm-none" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <span class="visually-hidden">Search</span>
                            <i class="ri-search-2-line text-muted"></i>
                        </button>

                       
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

                        <!-- Notification -->
                        <div class="dropdown features-dropdown">
                            <button type="button" class="btn icon-btn btn-text-primary rounded-circle position-relative"
                                id="page-header-notifications-dropdown" data-bs-toggle="dropdown"
                                data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false">
                                <i class="ri-notification-2-line fs-20"></i>
                                <span
                                    class="position-absolute translate-middle badge rounded-pill p-1 min-w-20px badge text-bg-danger">3</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                                aria-labelledby="page-header-notifications-dropdown">
                                <div class="dropdown-header d-flex align-items-center py-3">
                                    <h6 class="mb-0 me-auto">Notification</h6>
                                    <div class="d-flex align-items-center h6 mb-0">
                                        <span class="badge bg-primary me-2">8 New</span>

                                        <div class="dropdown">
                                            <a href="#!" class="btn btn-text-primary rounded-pill icon-btn-sm"
                                                id="remix-cion-notifications-dropdown-settings"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="visually-hidden">Notification Settings</span>
                                                <i class="ri-more-2-fill"></i>
                                            </a>

                                            <div class="dropdown-menu dropdown-menu-end"
                                                aria-labelledby="remix-cion-notifications-dropdown-settings">
                                                <span class="dropdown-header fw-medium text-body">Settings</span>
                                                <a class="dropdown-item" href="#!">
                                                    <i class="ri-archive-line"></i> Archive all
                                                </a>
                                                <a class="dropdown-item" href="#!">
                                                    <i class="ri-checkbox-circle-line"></i> Mark all as read
                                                </a>
                                                <a class="dropdown-item" href="#!">
                                                    <i class="ri-notification-off-line"></i> Disable notifications
                                                </a>
                                                <a class="dropdown-item" href="#!">
                                                    <i class="ri-gift-line"></i> What's new?
                                                </a>
                                                <div class="dropdown-divider"></div>
                                                <span class="dropdown-header fw-medium text-body">Feedback</span>
                                                <a class="dropdown-item" href="#!">
                                                    <i class="ri-chat-1-line"></i> Report
                                                </a>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                                <ul class="list-group list-group-flush header-notification-scrollable" data-simplebar>
                                    <li class="list-group-item list-group-item-action border-start-0 border-end-0">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-3">
                                                <div
                                                    class="avatar-item avatar avatar-title bg-danger-subtle text-danger">
                                                    CF
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 small">Charles Franklin</h6>
                                                <small class="mb-1 d-block text-body">Accepted your connection</small>
                                                <small class="text-muted">12hr ago</small>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="list-group-item list-group-item-action border-start-0 border-end-0">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-3">
                                                <div class="avatar-item avatar">
                                                    <img class="img-fluid avatar"
                                                        src="assets/images/avatar/avatar-9.jpg" alt="avatar image">
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 small">Jenny Wilson</h6>
                                                <small class="mb-0 d-block text-body">Create New Apps</small>
                                                <small class="text-muted">14hr ago</small>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="list-group-item list-group-item-action border-start-0 border-end-0">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-3 position-relative">
                                                <div class="avatar-item avatar">
                                                    <img class="img-fluid avatar"
                                                        src="assets/images/avatar/avatar-6.jpg" alt="avatar image">
                                                </div>
                                                <span
                                                    class="position-absolute border-2 border border-white h-12px w-12px rounded-circle bg-success end-0 top-0"></span>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 small">Ralph Edwards</h6>
                                                <div class="d-block">
                                                    <small>Wants to edit</small>
                                                    <small class="mb-1 text-body fw-semibold">Ownuh Design
                                                        system</small>
                                                </div>
                                                <small class="text-muted">12hr ago</small>
                                                <div class="d-flex align-items-center gap-2 mt-3">
                                                    <button class="btn btn-primary btn-sm">Accept</button>
                                                    <button class="btn btn-light-secondary btn-sm">Deny</button>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="list-group-item list-group-item-action border-start-0 border-end-0">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-3">
                                                <div
                                                    class="avatar-item avatar avatar-title bg-danger-subtle text-danger">
                                                    JJ
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 small">Jacob Jones</h6>
                                                <div class="d-block">
                                                    <small>Mentioned you in</small>
                                                    <small class="mb-1 text-body fw-semibold">Rewrite Button
                                                        components</small>
                                                </div>
                                                <small class="text-muted">15hr ago</small>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="list-group-item list-group-item-action border-start-0 border-end-0">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-3">
                                                <div
                                                    class="avatar-item avatar avatar-title bg-success-subtle text-success">
                                                    <i class="ri-pie-chart-2-line"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 small">Monthly report is generated</h6>
                                                <small class="mb-1 d-block text-body">July monthly financial report is
                                                    generated </small>
                                                <small class="text-muted">3 days ago</small>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="list-group-item list-group-item-action border-start-0 border-end-0">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0 me-3">
                                                <div
                                                    class="avatar-item avatar avatar-title bg-warning-subtle text-warning">
                                                    <i class="ri-error-warning-line"></i>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 small">CPU is running high</h6>
                                                <small class="mb-1 d-block text-body">CPU Utilization Percent is
                                                    currently at 88.63%,</small>
                                                <small class="text-muted">5 days ago</small>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
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