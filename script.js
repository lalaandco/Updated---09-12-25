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