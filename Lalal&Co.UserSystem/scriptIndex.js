function showForm (formId){
    document.querySelectorAll(".form-box").forEach(form => form.classList.remove("active"));
    document.getElementById(formId).classList.add("active");
}
function block_backround(login) {
    document.querySelector('.overlay').style.display = 'block';
}

const header = document.querySelector('header');

window.addEventListener('scroll', function() {
    header.classList.toggle('sticky', window.scrollY > 0);
});

localStorage.setItem('isLoggedIn', 'true');

localStorage.removeItem('isLoggedIn');

function showPassword() {
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePassword');
    
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        toggleIcon.classList.remove('bx-show');
        toggleIcon.classList.add('bx-hide');
    } else {
        passwordInput.type = "password";
        toggleIcon.classList.remove('bx-hide');
        toggleIcon.classList.add('bx-show');
    }
}

const sr = ScrollReveal({
    distance: '60px',
    duration: 2500,
    delay: 400,

})

sr.reveal('.home-text', {delay: 200, origin: 'top'})
sr.reveal('.home-img', {delay: 450, origin: 'top'})
sr.reveal('.about, .product, .cta, .footer', {delay: 200, origin: 'bottom'})
sr.reveal('.about-img, .product-card, .cta-content h2', {delay: 450, origin: 'left'})
sr.reveal('.about-text, .product-btn, .cta-content p, .cta-content a', {delay: 450, origin: 'right'})
