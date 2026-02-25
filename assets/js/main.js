document.addEventListener("DOMContentLoaded", function () {
    const navToggle = document.getElementById("navToggle");
    const mainNav = document.getElementById("mainNav");
    const backToTop = document.getElementById("backToTop");
    const yearSpan = document.getElementById("year");

    if (yearSpan) {
        yearSpan.textContent = new Date().getFullYear();
    }

    if (navToggle && mainNav) {
        navToggle.addEventListener("click", function () {
            mainNav.classList.toggle("open");
            navToggle.classList.toggle("open");
        });
    }

    window.addEventListener("scroll", function () {
        if (!backToTop) return;
        if (window.scrollY > 320) {
            backToTop.classList.add("visible");
        } else {
            backToTop.classList.remove("visible");
        }
    });

    if (backToTop) {
        backToTop.addEventListener("click", function (e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: "smooth" });
        });
    }
});
