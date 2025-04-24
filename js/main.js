(function () {
  const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
  const savedMode = localStorage.getItem("color-mode");
  const root = document.documentElement;

  function applyTheme(mode) {
    localStorage.setItem("color-mode", mode);
    root.style.setProperty("--initial-color-mode", mode);

    const themeVars = {
      primaryColor: { light: "#E7E7E7", dark: "#2E2E2E" },
      primaryColorDarkerShadow: { light: "#cecece", dark: "#252525" },
      primaryColorLighterShadow: { light: "#FFFFFF", dark: "#353434" },
      primaryTextColor: { light: "#000000", dark: "#ffffff" },
      offsetTextColor: { light: "#373737", dark: "#FFFFFF" },
    };

    for (const [key, value] of Object.entries(themeVars)) {
      root.style.setProperty(`--${key}`, value[mode]);
    }

    root.style.setProperty(
      "--toggleDistance",
      mode === "light" ? "27.5px" : "2.5px"
    );
    root.style.setProperty("--sunOpacity", mode === "light" ? "1" : "0");
    root.style.setProperty("--moonOpacity", mode === "light" ? "0" : "1");
    root.style.setProperty(
      "--toggleColor",
      mode === "light" ? "#EA984E" : "#DEC846"
    );
  }

  let currentMode =
    typeof savedMode === "string" ? savedMode : prefersDark ? "dark" : "light";
  applyTheme(currentMode);

  const toggleBtn = document.getElementById("dark-toggle");
  toggleBtn.addEventListener("click", () => {
    currentMode = currentMode === "light" ? "dark" : "light";
    applyTheme(currentMode);
  });
})();
