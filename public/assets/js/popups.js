const popupOverlay = document.getElementById("popup-overlay");
const popupMessage = document.getElementById("popup-message");
const popupClose = document.getElementById("popup-close");

function showPopup(message, color) {
  popupMessage.textContent = message;
  popupMessage.style.color = color;
  popupOverlay.classList.remove("hidden");
}

function hidePopup() {
  popupOverlay.classList.add("hidden");
}

popupClose.addEventListener("click", hidePopup);
popupOverlay.addEventListener("click", (e) => {
  if (e.target === popupOverlay) {
    hidePopup();
  }
});