var form = document.getElementById('registerForm');
var errorDiv = document.getElementById('error');

form.addEventListener('submit', function(e) {
    e.preventDefault();

    var nom = document.getElementById('nom').value.trim();
    var prenom = document.getElementById('prenom').value.trim();
    var email = document.getElementById('email').value.trim();
    var cin = document.getElementById('cin').value.trim();
    var photo = document.getElementById('photo').files[0];
    var pseudo = document.getElementById('pseudo').value.trim();
    var password = document.getElementById('password').value;

    var lettersRegex = /^[A-Za-zÀ-ÖØ-öø-ÿ\s'-]+$/;
    var cinRegex = /^[0-9]{8}$/;
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var pseudoRegex = /^[A-Za-z]+$/;
    var passwordRegex = /^[a-zA-Z0-9]{8,}[$#]$/;
    var allowedPhotoTypes = ['image/jpeg', 'image/png', 'image/jpg'];

    if (!lettersRegex.test(nom) || nom.length < 2) {
        showError("Le nom doit contenir uniquement des lettres et au minimum 2 lettres.");
        return;
    }
    if (!lettersRegex.test(prenom) || prenom.length < 2) {
        showError("Le prénom doit contenir uniquement des lettres et au minimum 2 lettres.");
        return;
    }
    if (!emailRegex.test(email)) {
        showError("Email invalide.");
        return;
    }
    if (!cinRegex.test(cin)) {
        showError("Le CIN doit contenir exactement 8 chiffres.");
        return;
    }
    if (!photo) {
        showError("Veuillez choisir une photo d'identité.");
        return;
    }
    if (allowedPhotoTypes.indexOf(photo.type) === -1) {
        showError("La photo doit être au format JPG ou PNG.");
        return;
    }
    if (!pseudoRegex.test(pseudo) || pseudo.length < 3) {
        showError("Le pseudo doit contenir uniquement des lettres et au minimum 3 caractères.");
        return;
    }
    if (!passwordRegex.test(password)) {
        showError("Le mot de passe doit contenir au moins 8 lettres/chiffres et finir par $ ou #.");
        return;
    }
    errorDiv.style.display = 'none';
    form.submit();
});

function showError(message) {
    errorDiv.style.display = 'block';
    errorDiv.textContent = message;
}
