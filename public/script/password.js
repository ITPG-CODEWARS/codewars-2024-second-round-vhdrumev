function togglePassword(inputId, toggleId) {
    const input = document.getElementById(inputId);
    const toggle = document.getElementById(toggleId);
    if (input.type === "password") { // if input is password type
        input.type = "text"; // make text
        toggle.textContent = "Hide"; // text update
    } else {
        input.type = "password"; // hide text (password)
        toggle.textContent = "Show"; // text update
    }
}