document.getElementById("registerForm").addEventListener("submit", function(e){

    let nom = document.getElementById("nom").value.trim();
    let prenom = document.getElementById("prenom").value.trim();
    let cin = document.getElementById("cin").value.trim();
    let email = document.getElementById("email").value.trim();
    let pseudo = document.getElementById("pseudo").value.trim();
    let password = document.getElementById("password").value;

    let error = document.getElementById("error");

    error.textContent = "";
    error.style.display = "none";

    let lettersRegex = /^[A-Za-zÀ-ÖØ-öø-ÿ\s'-]+$/;
    let cinRegex = /^[0-9]{8}$/;
    let emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    let pseudoRegex = /^[A-Za-z]+$/;
    let passwordRegex = /^[a-zA-Z0-9]{8,}[$#]$/;

    if(!lettersRegex.test(nom) || nom.length < 2){
        error.textContent = "Le nom doit contenir uniquement des lettres et au minimum 2 lettres";
        error.style.display = "block";
        e.preventDefault();
        return;
    }

    if(!lettersRegex.test(prenom) || prenom.length < 2){
        error.textContent = "Le prénom doit contenir uniquement des lettres et au minimum 2 lettres";
        error.style.display = "block";
        e.preventDefault();
        return;
    }

    if(!cinRegex.test(cin)){
        error.textContent = "Le CIN doit contenir exactement 8 chiffres";
        error.style.display = "block";
        e.preventDefault();
        return;
    }

    if(!emailRegex.test(email)){
        error.textContent = "Email invalide";
        error.style.display = "block";
        e.preventDefault();
        return;
    }

    if(!pseudoRegex.test(pseudo) || pseudo.length < 3){
        error.textContent = "Le pseudo doit contenir uniquement des lettres et au minimum 3 caractères";
        error.style.display = "block";
        e.preventDefault();
        return;
    }

    if(!passwordRegex.test(password)){
        error.textContent = "Le mot de passe doit contenir au moins 8 lettres/chiffres et finir par $ ou #";
        error.style.display = "block";
        e.preventDefault();
        return;
    }

    error.style.display = "none";

});
