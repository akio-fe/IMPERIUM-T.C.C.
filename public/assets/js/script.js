let card = document.querySelector(".card");
let loginButton = document.querySelector(".loginButton");
let cadastroButton = document.querySelector(".cadastroButton");

if (card && loginButton && cadastroButton) {
    loginButton.onclick = () => {
        card.classList.remove("cadastroActive")
        card.classList.add("loginActive")
    }

    cadastroButton.onclick = () => {
        card.classList.remove("loginActive")
        card.classList.add("cadastroActive")
    }
} else {
    console.warn("Login/Cadastro toggle not initialized. Missing card or button elements.");
}