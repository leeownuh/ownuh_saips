document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("globalSearchForm");
    const desktopInput = document.getElementById("globalSearchInput");
    const mobileInput = document.getElementById("globalSearchInputMobile");
    const mobileButton = document.getElementById("globalSearchMobileButton");
    const menu = document.getElementById("globalSearchMenu");
    const results = document.getElementById("globalSearchResults");

    if (!menu || !results) {
        return;
    }

    const searchRoot = menu.closest(".dropdown");
    const dropdownToggle = desktopInput ?? mobileButton;
    const dropdownInstance = dropdownToggle ? bootstrap.Dropdown.getOrCreateInstance(dropdownToggle, { autoClose: true }) : null;

    const pages = [
        { title: "Security Dashboard", url: "dashboard.php", keywords: ["dashboard", "overview", "security", "stats", "kpi"] },
        { title: "Users", url: "users.php", keywords: ["users", "accounts", "people", "directory"] },
        { title: "User Roles", url: "users-roles.php", keywords: ["roles", "permissions", "rbac", "access"] },
        { title: "User MFA", url: "users-mfa.php", keywords: ["mfa", "multi factor", "totp", "webauthn"] },
        { title: "Active Sessions", url: "sessions-active.php", keywords: ["sessions", "active sessions", "login sessions"] },
        { title: "Revoke Sessions", url: "sessions-revoke.php", keywords: ["revoke", "sessions", "terminate"] },
        { title: "Audit Log", url: "audit-log.php", keywords: ["audit", "logs", "events", "trail"] },
        { title: "Incident Response", url: "incidents-list.php", keywords: ["incidents", "response", "alerts", "sev1", "sev2"] },
        { title: "File Incident Report", url: "incidents-report.php", keywords: ["incident report", "report incident", "breach"] },
        { title: "Blocked IPs", url: "ips-blocked-ips.php", keywords: ["blocked ip", "ips", "firewall", "blocks"] },
        { title: "Brute Force Protection", url: "ips-brute-force.php", keywords: ["brute force", "attacks", "login attempts"] },
        { title: "Geo Block Rules", url: "ips-geo-block.php", keywords: ["geo", "country block", "rules"] },
        { title: "Rate Limits", url: "ips-rate-limits.php", keywords: ["rate limits", "throttling", "limits"] },
        { title: "Alert Rules", url: "settings-alert-rules.php", keywords: ["alert rules", "alerts", "thresholds"] },
        { title: "Compliance", url: "settings-compliance.php", keywords: ["compliance", "posture", "executive report", "controls"] },
        { title: "MFA Settings", url: "settings-mfa.php", keywords: ["mfa settings", "factor settings", "security settings"] },
        { title: "Policy Settings", url: "settings-policy.php", keywords: ["policy", "security policy", "settings"] },
        { title: "Login", url: "login.php", keywords: ["login", "sign in", "auth"] },
    ];

    function normalize(text) {
        return (text || "").toLowerCase().trim();
    }

    function scorePage(page, query) {
        const q = normalize(query);
        if (!q) {
            return 0;
        }

        const title = normalize(page.title);
        const url = normalize(page.url);
        const keywords = page.keywords.map(normalize);

        let score = 0;
        if (title.includes(q)) score += 8;
        if (url.includes(q)) score += 5;
        keywords.forEach((keyword) => {
            if (keyword.includes(q)) score += 4;
        });

        const queryTokens = q.split(/\s+/).filter(Boolean);
        queryTokens.forEach((token) => {
            if (title.includes(token)) score += 3;
            if (url.includes(token)) score += 2;
            keywords.forEach((keyword) => {
                if (keyword.includes(token)) score += 2;
            });
        });

        return score;
    }

    function searchPages(query) {
        return pages
            .map((page) => ({ ...page, score: scorePage(page, query) }))
            .filter((page) => page.score > 0)
            .sort((a, b) => b.score - a.score || a.title.localeCompare(b.title))
            .slice(0, 8);
    }

    function renderResults(query) {
        const matches = searchPages(query);

        if (!normalize(query)) {
            results.innerHTML = '<div class="px-3 py-2 text-muted fs-12">Start typing to search the main pages.</div>';
            return;
        }

        if (matches.length === 0) {
            results.innerHTML = '<div class="px-3 py-3 text-muted fs-13">No matching pages found.</div>';
            return;
        }

        results.innerHTML = matches.map((page, index) => `
            <a href="${page.url}" class="dropdown-item d-flex align-items-start justify-content-between gap-3 px-3 py-2 search-result-item" data-search-index="${index}">
                <div>
                    <div class="fw-semibold fs-13">${page.title}</div>
                    <div class="text-muted fs-12">${page.url}</div>
                </div>
                <i class="ri-arrow-right-up-line text-muted"></i>
            </a>
        `).join("");
    }

    function openMenu(focusMobile = false) {
        searchRoot?.classList.add("show");
        menu.classList.add("show");
        dropdownToggle?.setAttribute("aria-expanded", "true");
        if (focusMobile) {
            mobileInput?.focus();
        }
    }

    function closeMenu() {
        searchRoot?.classList.remove("show");
        menu.classList.remove("show");
        dropdownToggle?.setAttribute("aria-expanded", "false");
    }

    function goToBestMatch(query) {
        const matches = searchPages(query);
        if (matches.length > 0) {
            window.location.href = matches[0].url;
        }
    }

    function bindSearchInput(input, isMobile = false) {
        if (!input) {
            return;
        }

        input.addEventListener("focus", () => openMenu(isMobile));
        input.addEventListener("input", () => {
            renderResults(input.value);
            openMenu(isMobile);
        });
        input.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                goToBestMatch(input.value);
            }
            if (event.key === "Escape") {
                closeMenu();
                input.blur();
            }
        });
    }

    bindSearchInput(desktopInput, false);
    bindSearchInput(mobileInput, true);

    form?.addEventListener("submit", (event) => {
        event.preventDefault();
        goToBestMatch(desktopInput?.value || "");
    });

    mobileButton?.addEventListener("click", () => {
        openMenu(true);
        renderResults(mobileInput?.value || desktopInput?.value || "");
    });

    document.addEventListener("click", (event) => {
        if (!searchRoot?.contains(event.target)) {
            closeMenu();
        }
    });

    results.addEventListener("click", () => {
        closeMenu();
    });

    syncFromDesktopToMobile();

    function syncFromDesktopToMobile() {
        if (!desktopInput || !mobileInput) {
            return;
        }

        desktopInput.addEventListener("input", () => {
            mobileInput.value = desktopInput.value;
        });

        mobileInput.addEventListener("input", () => {
            desktopInput.value = mobileInput.value;
        });
    }
});
