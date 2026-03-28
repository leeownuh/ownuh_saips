function updateLayout() {
    function applyTheme(themeMode) {
        let resolvedTheme = themeMode;

        if (themeMode === THEME_MODES.SYSTEM) {
            resolvedTheme = window.matchMedia("(prefers-color-scheme: dark)").matches
                ? THEME_MODES.DARK
                : THEME_MODES.LIGHT;
        }

        document.documentElement.setAttribute(ATTRIBUTES.THEME, resolvedTheme);
        sessionStorage.setItem(ATTRIBUTES.THEME, themeMode);
    }

    function updateActiveState(attributeName, value) {
        document.querySelectorAll(`[data-attribute="${attributeName}"]`).forEach((input) => {
            const switcherCard = input.nextElementSibling;

            if (switcherCard && switcherCard.classList.contains("switcher-card")) {
                switcherCard.classList.remove("active");
                return;
            }

            if (attributeName === "data-font-body" || attributeName === "data-font-heading") {
                input.value = value;
                return;
            }

            const listItem = input.closest(".list-group-item");
            if (listItem?.classList.contains("form-check")) {
                listItem.classList.remove("active");
            }
        });

        const activeInput = document.querySelector(`[data-attribute="${attributeName}"][value="${value}"]`);
        if (!activeInput) {
            return;
        }

        const switcherCard = activeInput.nextElementSibling;
        if (switcherCard && switcherCard.classList.contains("switcher-card")) {
            switcherCard.classList.add("active");
            return;
        }

        if (attributeName === "data-font-body" || attributeName === "data-font-heading") {
            activeInput.value = value;
            return;
        }

        const listItem = activeInput.closest(".list-group-item");
        if (listItem?.classList.contains("form-check")) {
            listItem.classList.add("active");
        }
    }

    function rebuildSidebarMenu() {
        setTimeout(() => {
            const sidebarMenu = document.getElementById("sidebarMenu");
            if (!sidebarMenu) {
                return;
            }

            const allMenuItems = document.getElementById("all-menu-items");
            if (allMenuItems) {
                sidebarMenu.innerHTML = allMenuItems.parentElement.innerHTML;
            }

            sidebarMenu.classList.add("simplebar");

            if (window.SimpleBar) {
                new SimpleBar(sidebarMenu);
                dropdownInit();
            }
        }, 100);
    }

    function initializeLayout() {
        Object.keys(ATTRIBUTES).forEach((key) => {
            const attributeName = ATTRIBUTES[key];
            const storedValue = sessionStorage.getItem(attributeName) || DEFAULT_VALUES[attributeName];

            document.documentElement.setAttribute(attributeName, storedValue);

            if (attributeName !== ATTRIBUTES.AUTH_LAYOUT) {
                sessionStorage.setItem(attributeName, storedValue);
            }

            updateActiveState(attributeName, storedValue);

            if (attributeName === ATTRIBUTES.MAIN_LAYOUT) {
                if (storedValue === "horizontal") {
                    document.getElementById("sidebarMenu")?.classList.add("container-fluid");
                } else {
                    document.getElementById("sidebarMenu")?.classList.remove("container-fluid");
                }

                if (storedValue === "vertical" || storedValue === "two-column") {
                    rebuildSidebarMenu();
                }
            }

            if (attributeName === ATTRIBUTES.DIRECTION_MODE) {
                updateLayoutDirection(storedValue);
            }
        });

        applyTheme(sessionStorage.getItem(ATTRIBUTES.THEME) || DEFAULT_VALUES[ATTRIBUTES.THEME]);
    }

    function applyAttribute(attributeName, value) {
        if (attributeName === ATTRIBUTES.MAIN_LAYOUT) {
            if (value === "vertical" || value === "two-column") {
                rebuildSidebarMenu();
                setTimeout(() => {
                    findActiveMenu(value);
                }, 0);
            } else {
                setTimeout(() => {
                    setTimeout(() => {
                        const sidebarMenu = document.getElementById("sidebarMenu");
                        if (!sidebarMenu || !sidebarMenu.classList.contains("simplebar") || !window.SimpleBar) {
                            return;
                        }

                        const simplebarInstance = SimpleBar.instances.get(sidebarMenu);
                        if (!simplebarInstance) {
                            return;
                        }

                        simplebarInstance.unMount();
                        const allMenuItems = document.getElementById("all-menu-items");
                        if (allMenuItems) {
                            sidebarMenu.innerHTML = allMenuItems.parentElement.innerHTML;
                        }
                        dropdownInit();
                    }, 500);
                }, 0);
            }

            if (value === "horizontal") {
                document.getElementById("sidebarMenu")?.classList.add("container-fluid");
            } else {
                document.getElementById("sidebarMenu")?.classList.remove("container-fluid");
            }
        }

        if (attributeName !== ATTRIBUTES.AUTH_LAYOUT) {
            sessionStorage.setItem(attributeName, value);
        }

        document.documentElement.setAttribute(attributeName, value);

        if (attributeName === ATTRIBUTES.DIRECTION_MODE) {
            updateLayoutDirection(value);
        }

        if (attributeName === ATTRIBUTES.THEME) {
            applyTheme(value);
        }

        updateActiveState(attributeName, value);
    }

    function resetSettings() {
        Object.keys(DEFAULT_VALUES).forEach((key) => {
            applyAttribute(key, DEFAULT_VALUES[key]);
        });
    }

    function hidePreloader() {
        const preloader = document.getElementById("preloader");

        if (preloader && ATTRIBUTES.PAGE_LOADER !== "hidden") {
            setTimeout(() => {
                preloader.classList.add("hidden");
            }, 1500);
        } else {
            preloader?.classList.add("hidden");
        }
    }

    function setupLayout() {
        hidePreloader();

        if (DEFAULT_VALUES.AUTH_LAYOUT === false) {
            initializeLayout();

            const switcher = document.getElementById("switcher");
            if (switcher) {
                switcher.addEventListener("change", (event) => {
                    const input = event.target;
                    const attributeName = input.getAttribute("data-attribute");
                    if (!attributeName) {
                        return;
                    }

                    applyAttribute(attributeName, input.value);
                    updateActiveState(attributeName, input.value);
                });
            }

            const resetButton = document.getElementById("resetSettings");
            resetButton?.addEventListener("click", resetSettings);
        }

        window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => {
            if (sessionStorage.getItem(ATTRIBUTES.THEME) === THEME_MODES.SYSTEM) {
                applyTheme(THEME_MODES.SYSTEM);
            }
        });
    }

    updateLayoutDirection = (direction) => {
        setTimeout(() => {
            const bootstrapCss = document.getElementById("bootstrap-style");
            const appCss = document.getElementById("app-style");
            const customCss = document.getElementById("custom-style");

            if (!bootstrapCss || !appCss) {
                return;
            }

            if (direction === "rtl") {
                bootstrapCss.href = bootstrapCss.href.replace("bootstrap.min.css", "bootstrap-rtl.min.css");
                appCss.href = appCss.href.replace("app.min.css", "app-rtl.min.css");
                customCss.href = customCss.href.replace("custom.min.css", "custom-rtl.min.css");
            } else {
                bootstrapCss.href = bootstrapCss.href.replace("bootstrap-rtl.min.css", "bootstrap.min.css");
                appCss.href = appCss.href.replace("app-rtl.min.css", "app.min.css");
                customCss.href = customCss.href.replace("custom-rtl.min.css", "custom.min.css");
            }
        }, 100);
    };

    setTimeout(() => {
        const sidebarToggle = document.querySelector(".sidebar-toggle");
        const horizontalToggle = document.querySelector(".small-screen-horizontal-toggle");
        const root = document.documentElement;
        const sidebar = document.querySelector(".app-sidebar");
        const overlay = document.querySelector(".horizontal-overlay");

        sidebarToggle?.addEventListener("click", () => {
            applyAttribute(
                ATTRIBUTES.MAIN_LAYOUT,
                root.getAttribute(ATTRIBUTES.MAIN_LAYOUT) === "small-icon" ? "vertical" : "small-icon"
            );
        });

        horizontalToggle?.addEventListener("click", () => {
            sidebar?.classList.toggle("show");
            overlay?.classList.toggle("show");
        });
    }, 100);

    document.addEventListener("DOMContentLoaded", () => {
        setupLayout();

        const lightTheme = document.getElementById("light-theme");
        const darkTheme = document.getElementById("dark-theme");
        const systemTheme = document.getElementById("system-theme");

        lightTheme?.addEventListener("click", () => {
            applyTheme(THEME_MODES.LIGHT);
            updateActiveState("data-bs-theme", "light");
        });

        darkTheme?.addEventListener("click", () => {
            applyTheme(THEME_MODES.DARK);
            updateActiveState("data-bs-theme", "dark");
        });

        systemTheme?.addEventListener("click", () => {
            applyTheme(THEME_MODES.SYSTEM);
            updateActiveState("data-bs-theme", "auto");
        });
    });

    if (DEFAULT_VALUES.AUTH_LAYOUT === false) {
        setupLayout();

        window.addEventListener("resize", () => {
            const currentLayout = sessionStorage.getItem("data-main-layout");
            if (!currentLayout || currentLayout === "horizontal") {
                return;
            }

            if (window.innerWidth < 768.99) {
                sessionStorage.setItem(ATTRIBUTES.MAIN_LAYOUT, "close-sidebar");
            } else if (window.innerWidth < 1250) {
                sessionStorage.setItem(ATTRIBUTES.MAIN_LAYOUT, "small-icon");
            } else {
                sessionStorage.setItem(ATTRIBUTES.MAIN_LAYOUT, "vertical");
            }

            document.documentElement.setAttribute(
                ATTRIBUTES.MAIN_LAYOUT,
                sessionStorage.getItem(ATTRIBUTES.MAIN_LAYOUT)
            );
        });
    } else {
        document.documentElement.setAttribute("data-main-layout", "close-sidebar");
        document.documentElement.setAttribute("data-nav-position", "hidden");
    }

    hidePreloader();
}

updateLayout();
