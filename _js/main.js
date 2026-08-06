document.addEventListener('DOMContentLoaded', () => {
    const burgerToggle = document.getElementById('burgerToggle');
    const navLinks = document.querySelector('.nav-links');
    const navRight = document.querySelector('.nav-right');

    if (burgerToggle && navLinks) {
        burgerToggle.addEventListener('click', () => {
            // On bascule la classe open sur le menu et sur le bouton burger
            navLinks.classList.toggle('open');
            navRight.classList.toggle('open');
            burgerToggle.classList.toggle('open');
        });
    }
});