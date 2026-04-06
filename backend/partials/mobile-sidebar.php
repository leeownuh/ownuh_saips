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
                                <li class="slide"><a href="login.php" class="side-menu__item">Sign In</a></li>
                                <li class="slide"><a href="otp-verify.php" class="side-menu__item">MFA Verify</a></li>
                                <li class="slide"><a href="auth-lockscreen.php" class="side-menu__item">Session Lock</a></li>
                                <li class="slide"><a href="forgot-password.php" class="side-menu__item">Reset Password</a></li>
                                <li class="slide"><a href="auth-create-password.php" class="side-menu__item">Create Password</a></li>
                            </ul>
                        </li>
                        <li class="menu-title" role="presentation">Intrusion Prevention</li>
                        <li class="slide has-sub"><a href="#!" class="side-menu__item"><span class="side_menu_icon"><i class="ri-radar-line"></i></span><span class="side-menu__label">IPS Monitor</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="ips-blocked-ips.php" class="side-menu__item">Blocked IPs</a></li>
                                <li class="slide"><a href="ips-brute-force.php" class="side-menu__item">Brute-Force Alerts</a></li>
                                <li class="slide"><a href="attack-attribution.php" class="side-menu__item">Attack Attribution</a></li>
                                <li class="slide"><a href="ips-geo-block.php" class="side-menu__item">Geo-Block Rules</a></li>
                                <li class="slide"><a href="ips-rate-limits.php" class="side-menu__item">Rate Limit Config</a></li>
                            </ul>
                        </li>
                        <li class="menu-title" role="presentation">Sessions</li>
                        <li class="slide has-sub"><a href="#!" class="side-menu__item"><span class="side_menu_icon"><i class="ri-computer-line"></i></span><span class="side-menu__label">Session Management</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="sessions-active.php" class="side-menu__item">Active Sessions</a></li>
                                <li class="slide"><a href="sessions-revoke.php" class="side-menu__item">Revoke Sessions</a></li>
                            </ul>
                        </li>
                        <li class="menu-title" role="presentation">Audit &amp; Monitoring</li>
                        <li class="slide has-sub"><a href="#!" class="side-menu__item"><span class="side_menu_icon"><i class="ri-file-list-3-line"></i></span><span class="side-menu__label">Audit Log</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="audit-log.php" class="side-menu__item">Full Audit Log</a></li>
                                <li class="slide"><a href="audit-log.html#auth" class="side-menu__item">Auth Events</a></li>
                                <li class="slide"><a href="audit-log.html#ips" class="side-menu__item">IPS Events</a></li>
                                <li class="slide"><a href="audit-log.html#admin" class="side-menu__item">Admin Events</a></li>
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
                                <li class="slide"><a href="users-roles.php" class="side-menu__item">Roles &amp; Permissions</a></li>
                            </ul>
                        </li>
                        <li class="slide has-sub"><a href="#!" class="side-menu__item"><span class="side_menu_icon"><i class="ri-settings-3-line"></i></span><span class="side-menu__label">System Settings</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="settings-policy.php" class="side-menu__item">Password Policy</a></li>
                                <li class="slide"><a href="settings-mfa.php" class="side-menu__item">MFA Config</a></li>
                                <li class="slide"><a href="settings-alert-rules.php" class="side-menu__item">Alert Rules</a></li>
                                <li class="slide"><a href="settings-compliance.php" class="side-menu__item">Compliance Checklist</a></li>
                            </ul>
                        </li>
                        <li class="menu-title" role="presentation">System</li>
                        <li class="slide has-sub"><a href="#!" class="side-menu__item"><span class="side_menu_icon"><i class="ri-error-warning-line"></i></span><span class="side-menu__label">Error Pages</span><i class="ri-arrow-down-s-line side-menu__angle"></i></a>
                            <ul class="slide-menu child1">
                                <li class="slide"><a href="auth-401.php" class="side-menu__item">401 Unauthorized</a></li>
                                <li class="slide"><a href="auth-404.php" class="side-menu__item">404 Not Found</a></li>
                                <li class="slide"><a href="auth-500.php" class="side-menu__item">500 Server Error</a></li>
                                <li class="slide"><a href="under-maintenance.php" class="side-menu__item">Maintenance</a></li>
                            </ul>
                        </li>
                    </ul>
                </nav>
            </aside>
        </div>
    </div>
    <!-- END SMALL SCREEN SIDEBAR -->
