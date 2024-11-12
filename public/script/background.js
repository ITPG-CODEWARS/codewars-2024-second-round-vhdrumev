const gradients = [
    'linear-gradient(135deg, #6a11cb, #2575fc)',  // Purple to Blue Gradient
    'linear-gradient(135deg, #121212, #242424)'
    //'linear-gradient(135deg, #f8cdda, #1e3c72)'  // Light Pink to Navy Gradient
];

// set a random gradient from the list
function setRandomGradient() {
    const randomIndex = Math.floor(Math.random() * gradients.length); // random
    const selectedGradient = gradients[randomIndex]; // select
    document.body.style.background = selectedGradient; // apply
}

// Call the function to set the random gradient on page load
window.onload = setRandomGradient;