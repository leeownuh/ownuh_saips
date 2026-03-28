<?php
/**
 * Ownuh SAIPS — Sidebar Partial
 * Active page detection — CAP512 Unit II + III
 */
$current_page = basename($_SERVER['PHP_SELF']);
function nav_active(string $href, string $current): string {
    return basename($href) === $current ? 'active' : '';
}
?>
<aside class="app-sidebar">
        <!-- START BRAND LOGO -->
        <div class="app-sidebar-logo px-6 justify-content-center align-items-center">
            <a href="dashboard.php">
                <img height="70" class="app-sidebar-logo-default" alt="Logo" src="assets/images/light-logo.png">
                <img height="40" class="app-sidebar-logo-minimize" alt="Logo" src="assets/images/Favicon.png">
            </a>
        </div>
        <!-- END BRAND LOGO -->
        <nav class="app-sidebar-menu nav nav-pills flex-column fs-6" id="sidebarMenu" aria-label="Main navigation">
            <ul class="main-menu" id="all-menu-items" role="menu">

                <!-- ─── OVERVIEW ─────────────────────────────────── -->
                <li class="menu-title" role="presentation">Overview</li>

                <li class="slide has-sub active">
                    <a href="dashboard.php" class="side-menu__item <?= nav_active('index.html', $current_page) ?>" role="menuitem">
                        <span class="side_menu_icon"><i class="ri-shield-line"></i></span>
                        <span class="side-menu__label">Security Dashboard</span>
                    </a>
                </li>

                <!-- ─── AUTHENTICATION ────────────────────────────── -->
                <li class="menu-title" role="presentation">Authentication</li>

                <li class="slide has-sub">
                    <a href="#!" class="side-menu__item" role="menuitem">
                        <span class="side_menu_icon"><i class="ri-login-box-line"></i></span>
                        <span class="side-menu__label">Auth Gateway</span>
                        <i class="ri-arrow-down-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="login.php" class="side-menu__item" role="menuitem">Sign In</a></li>
                        <li class="slide"><a href="otp-verify.php" class="side-menu__item" role="menuitem">MFA Verify</a></li>
                        <li class="slide"><a href="auth-lockscreen.php" class="side-menu__item" role="menuitem">Session Lock</a></li>
                        <li class="slide"><a href="forgot-password.php" class="side-menu__item" role="menuitem">Reset Password</a></li>
                        <li class="slide"><a href="auth-create-password.php" class="side-menu__item" role="menuitem">Create Password</a></li>
                    </ul>
                </li>

                <!-- ─── INTRUSION PREVENTION ──────────────────────── -->
                <li class="menu-title" role="presentation">Intrusion Prevention</li>

                <li class="slide has-sub">
                    <a href="#!" class="side-menu__item" role="menuitem">
                        <span class="side_menu_icon"><i class="ri-radar-line"></i></span>
                        <span class="side-menu__label">IPS Monitor</span>
                        <i class="ri-arrow-down-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="ips-blocked-ips.php" class="side-menu__item" role="menuitem">Blocked IPs</a></li>
                        <li class="slide"><a href="ips-brute-force.php" class="side-menu__item" role="menuitem">Brute-Force Alerts</a></li>
                        <li class="slide"><a href="ips-geo-block.php" class="side-menu__item" role="menuitem">Geo-Block Rules</a></li>
                        <li class="slide"><a href="ips-rate-limits.php" class="side-menu__item" role="menuitem">Rate Limit Config</a></li>
                    </ul>
                </li>

                <!-- ─── SESSION MANAGEMENT ────────────────────────── -->
                <li class="menu-title" role="presentation">Sessions</li>

                <li class="slide has-sub">
                    <a href="#!" class="side-menu__item" role="menuitem">
                        <span class="side_menu_icon"><i class="ri-computer-line"></i></span>
                        <span class="side-menu__label">Session Management</span>
                        <i class="ri-arrow-down-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="sessions-active.php" class="side-menu__item" role="menuitem">Active Sessions</a></li>
                        <li class="slide"><a href="sessions-revoke.php" class="side-menu__item" role="menuitem">Revoke Sessions</a></li>
                    </ul>
                </li>

                <!-- ─── AUDIT LOGS ─────────────────────────────────── -->
                <li class="menu-title" role="presentation">Audit &amp; Monitoring</li>

                <li class="slide has-sub">
                    <a href="#!" class="side-menu__item" role="menuitem">
                        <span class="side_menu_icon"><i class="ri-file-list-3-line"></i></span>
                        <span class="side-menu__label">Audit Log</span>
                        <i class="ri-arrow-down-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="audit-log.php" class="side-menu__item" role="menuitem">Full Audit Log</a></li>
                        <li class="slide"><a href="audit-log.html#auth" class="side-menu__item" role="menuitem">Auth Events</a></li>
                        <li class="slide"><a href="audit-log.html#ips" class="side-menu__item" role="menuitem">IPS Events</a></li>
                        <li class="slide"><a href="audit-log.html#admin" class="side-menu__item" role="menuitem">Admin Events</a></li>
                    </ul>
                </li>

                <!-- ─── INCIDENT RESPONSE ─────────────────────────── -->
                <li class="menu-title" role="presentation">Incidents</li>

                <li class="slide has-sub">
                    <a href="#!" class="side-menu__item" role="menuitem">
                        <span class="side_menu_icon"><i class="ri-alarm-warning-line"></i></span>
                        <span class="side-menu__label">Incident Response</span>
                        <i class="ri-arrow-down-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="incidents-list.php" class="side-menu__item" role="menuitem">All Incidents</a></li>
                        <li class="slide"><a href="incidents-list.html#open" class="side-menu__item" role="menuitem">Open <span class="badge bg-danger ms-1">5</span></a></li>
                        <li class="slide"><a href="incidents-list.html#resolved" class="side-menu__item" role="menuitem">Resolved</a></li>
                        <li class="slide"><a href="incidents-report.php" class="side-menu__item" role="menuitem">File Incident Report</a></li>
                    </ul>
                </li>

                <!-- ─── USER MANAGEMENT ───────────────────────────── -->
                <li class="menu-title" role="presentation">Administration</li>

                <li class="slide has-sub">
                    <a href="#!" class="side-menu__item" role="menuitem">
                        <span class="side_menu_icon"><i class="ri-group-line"></i></span>
                        <span class="side-menu__label">User Management</span>
                        <i class="ri-arrow-down-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="users.php" class="side-menu__item" role="menuitem">All Users</a></li>
                        <li class="slide"><a href="users.php?status=locked" class="side-menu__item" role="menuitem">Locked Accounts <span class="badge bg-warning ms-1">1</span></a></li>
                        <li class="slide"><a href="users-mfa.php" class="side-menu__item" role="menuitem">MFA Status</a></li>
                        <li class="slide"><a href="users-roles.php" class="side-menu__item" role="menuitem">Roles &amp; Permissions</a></li>
                    </ul>
                </li>

                <li class="slide has-sub">
                    <a href="#!" class="side-menu__item" role="menuitem">
                        <span class="side_menu_icon"><i class="ri-settings-3-line"></i></span>
                        <span class="side-menu__label">System Settings</span>
                        <i class="ri-arrow-down-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="settings-policy.php" class="side-menu__item" role="menuitem">Password Policy</a></li>
                        <li class="slide"><a href="settings-mfa.php" class="side-menu__item" role="menuitem">MFA Config</a></li>
                        <li class="slide"><a href="settings-alert-rules.php" class="side-menu__item" role="menuitem">Alert Rules</a></li>
                        <li class="slide"><a href="settings-compliance.php" class="side-menu__item" role="menuitem">Compliance Checklist</a></li>
                    </ul>
                </li>

                <!-- ─── ERROR PAGES ───────────────────────────────── -->
                <li class="menu-title" role="presentation">System</li>

                <li class="slide has-sub">
                    <a href="#!" class="side-menu__item" role="menuitem">
                        <span class="side_menu_icon"><i class="ri-error-warning-line"></i></span>
                        <span class="side-menu__label">Error Pages</span>
                        <i class="ri-arrow-down-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide"><a href="auth-401.php" class="side-menu__item" role="menuitem">401 Unauthorized</a></li>
                        <li class="slide"><a href="auth-404.php" class="side-menu__item" role="menuitem">404 Not Found</a></li>
                        <li class="slide"><a href="auth-500.php" class="side-menu__item" role="menuitem">500 Server Error</a></li>
                        <li class="slide"><a href="under-maintenance.php" class="side-menu__item" role="menuitem">Maintenance</a></li>
                    </ul>
                </li>

            </ul>
        </nav>
    </aside>
    <!-- END SIDEBAR -->
