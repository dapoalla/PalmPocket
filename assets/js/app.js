(function () {
  "use strict";

  // Theme toggle: persists in localStorage, falls back to server-rendered default.
  var root = document.documentElement;
  var stored = localStorage.getItem("pp-theme");
  if (stored === "light" || stored === "dark") {
    root.setAttribute("data-theme", stored);
  }

  function setTheme(theme) {
    root.setAttribute("data-theme", theme);
    localStorage.setItem("pp-theme", theme);
    document.querySelectorAll("[data-theme-btn]").forEach(function (btn) {
      btn.classList.toggle("active", btn.getAttribute("data-theme-btn") === theme);
    });
  }

  document.querySelectorAll("[data-theme-btn]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      setTheme(btn.getAttribute("data-theme-btn"));
    });
    btn.classList.toggle("active", btn.getAttribute("data-theme-btn") === root.getAttribute("data-theme"));
  });

  // Expense/inflow form: toggle quantity vs loan checkbox.
  var typeSelect = document.getElementById("txType");
  if (typeSelect) {
    var sync = function () {
      var qtyWrap = document.getElementById("qtyWrap");
      var loanWrap = document.getElementById("loanWrap");
      if (qtyWrap) qtyWrap.style.display = typeSelect.value === "expense" ? "block" : "none";
      if (loanWrap) loanWrap.style.display = typeSelect.value === "inflow" ? "flex" : "none";
    };
    typeSelect.addEventListener("change", sync);
    sync();
  }

  // Auto-dismiss flash messages after a few seconds.
  document.querySelectorAll(".flash").forEach(function (el) {
    setTimeout(function () {
      el.style.transition = "opacity .4s ease";
      el.style.opacity = "0";
      setTimeout(function () { el.remove(); }, 400);
    }, 4500);
  });
})();
