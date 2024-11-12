// save scroll position on refresh

window.addEventListener("beforeunload", () => {
    localStorage.setItem("scrollPosition", window.scrollY); // save to local storage
});

// restore the scroll position when page is refreshed
window.addEventListener("load", () => {
    const scrollPosition = localStorage.getItem("scrollPosition"); // try to get
    if (scrollPosition)                                              // if successful get from local storage
        window.scrollTo(0, parseInt(scrollPosition, 10)); // restore
});