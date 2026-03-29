document.addEventListener("DOMContentLoaded", () => {
    const root = document.documentElement;
    const fullscreenButton = document.getElementById("fullscreen-button");
    const themeToggleButton = document.getElementById("theme-mode-button");
    const themeButtons = {
        light: document.getElementById("light-theme"),
        dark: document.getElementById("dark-theme"),
        system: document.getElementById("system-theme"),
    };

    function resolvedTheme(themeMode) {
        if (themeMode === "auto") {
            return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
        }

        return themeMode;
    }

    function syncSidebarTheme(themeMode) {
        const finalTheme = resolvedTheme(themeMode);
        const sidebarMode = finalTheme === "dark" ? "dark-sidebar" : "light-sidebar";
        root.setAttribute("data-sidebar", sidebarMode);
        sessionStorage.setItem("data-sidebar", sidebarMode);
    }

    function syncThemeButtonIcon() {
        const icon = themeToggleButton?.querySelector("i");
        if (!icon) {
            return;
        }

        const themeMode = sessionStorage.getItem("data-bs-theme") || root.getAttribute("data-bs-theme") || "light";
        icon.className = resolvedTheme(themeMode) === "dark"
            ? "ri-moon-clear-line fs-20"
            : "ri-sun-line fs-20";
    }

    function syncFullscreenButton() {
        if (!fullscreenButton) {
            return;
        }

        const active = Boolean(document.fullscreenElement);
        fullscreenButton.classList.toggle("active", active);
        fullscreenButton.setAttribute("aria-pressed", active ? "true" : "false");

        const iconOn = fullscreenButton.querySelector(".icon-on");
        const iconOff = fullscreenButton.querySelector(".icon-off");

        if (iconOn) {
            iconOn.style.display = active ? "inline-flex" : "none";
        }
        if (iconOff) {
            iconOff.style.display = active ? "none" : "inline-flex";
        }
    }

    async function requestFullscreenFor(element) {
        if (element.requestFullscreen) {
            await element.requestFullscreen();
            return;
        }

        if (element.webkitRequestFullscreen) {
            element.webkitRequestFullscreen();
            return;
        }

        if (element.msRequestFullscreen) {
            element.msRequestFullscreen();
            return;
        }

        throw new Error("Fullscreen API is not supported.");
    }

    async function exitFullscreen() {
        if (document.exitFullscreen) {
            await document.exitFullscreen();
            return;
        }

        if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
            return;
        }

        if (document.msExitFullscreen) {
            document.msExitFullscreen();
            return;
        }

        throw new Error("Exit fullscreen API is not supported.");
    }

    themeButtons.light?.addEventListener("click", () => {
        syncSidebarTheme("light");
        syncThemeButtonIcon();
    });

    themeButtons.dark?.addEventListener("click", () => {
        syncSidebarTheme("dark");
        syncThemeButtonIcon();
    });

    themeButtons.system?.addEventListener("click", () => {
        syncSidebarTheme("auto");
        syncThemeButtonIcon();
    });

    window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => {
        const currentTheme = sessionStorage.getItem("data-bs-theme") || "light";
        if (currentTheme === "auto") {
            syncSidebarTheme("auto");
            syncThemeButtonIcon();
        }
    });

    document.addEventListener("fullscreenchange", syncFullscreenButton);
    document.addEventListener("webkitfullscreenchange", syncFullscreenButton);
    document.addEventListener("msfullscreenchange", syncFullscreenButton);

    fullscreenButton?.addEventListener("click", async (event) => {
        event.preventDefault();
        event.stopPropagation();

        try {
            if (document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement) {
                await exitFullscreen();
            } else {
                await requestFullscreenFor(document.documentElement);
            }
        } catch (error) {
            console.error("Fullscreen toggle failed:", error);
        } finally {
            window.setTimeout(syncFullscreenButton, 50);
        }
    }, true);

    syncSidebarTheme(sessionStorage.getItem("data-bs-theme") || root.getAttribute("data-bs-theme") || "light");
    syncThemeButtonIcon();
    syncFullscreenButton();
});
