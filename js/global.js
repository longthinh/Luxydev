function toggleDarkMode() {
  const body = document.body;
  const icon = document.getElementById("mode-icon");

  if (body.classList.contains("light-mode")) {
    body.classList.remove("light-mode");
    body.classList.add("dark-mode");
    icon.src = "../images/Home/Night.png";
    localStorage.setItem("mode", "dark");
  } else {
    body.classList.remove("dark-mode");
    body.classList.add("light-mode");
    icon.src = "../images/Home/Day.png";
    localStorage.setItem("mode", "light");
  }
}

window.onload = () => {
  const savedMode = localStorage.getItem("mode");
  const body = document.body;
  const icon = document.getElementById("mode-icon");

  if (savedMode === "light") {
    body.classList.add("light-mode");
    body.classList.remove("dark-mode");
    icon.src = "../images/Home/Day.png";
  } else {
    body.classList.add("dark-mode");
    body.classList.remove("light-mode");
    icon.src = "../images/Home/Night.png";
  }
};
