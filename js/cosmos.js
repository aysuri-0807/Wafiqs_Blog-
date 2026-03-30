document.addEventListener("DOMContentLoaded", () => {
  /** @type {HTMLElement | null} */
  const starsLayer = document.querySelector(".space-stars");
  const sparklesLayer = document.querySelector(".space-sparkles");
  const orbitsLayer = document.querySelector(".space-orbits");
  const navBar = document.querySelector(".cosmic-nav");
  const mainContainer = document.querySelector("main.container");

  if (!starsLayer) {
    return;
  }

  const buildSparkles = () => {
    if (!sparklesLayer) return [];

    const total = window.innerWidth < 700 ? 36 : 64;
    const fragment = document.createDocumentFragment();
    const stars = [];

    for (let i = 0; i < total; i += 1) {
      const sparkle = document.createElement("span");
      sparkle.className = "sparkle-star";
      sparkle.style.left = `${Math.random() * 100}%`;
      sparkle.style.top = `${Math.random() * 100}%`;
      const size = 1 + Math.random() * 2.8;
      sparkle.style.width = `${size.toFixed(2)}px`;
      sparkle.style.height = `${size.toFixed(2)}px`;
      sparkle.style.setProperty("--sparkle-duration", `${(2 + Math.random() * 5).toFixed(2)}s`);
      sparkle.style.setProperty("--sparkle-delay", `${(-Math.random() * 6).toFixed(2)}s`);
      fragment.appendChild(sparkle);
      stars.push(sparkle);
    }

    sparklesLayer.replaceChildren(fragment);
    return stars;
  };

  const sparkles = buildSparkles();

  setInterval(() => {
    const randomOpacity = 0.78 + Math.random() * 0.22;
    const randomGlow = 4 + Math.random() * 9;
    starsLayer.style.opacity = randomOpacity.toFixed(2);
    starsLayer.style.filter = `drop-shadow(0 0 ${randomGlow.toFixed(1)}px rgba(187, 228, 255, 0.48))`;
  }, 380);

  if (sparkles.length > 0) {
    setInterval(() => {
      const sparkle = sparkles[Math.floor(Math.random() * sparkles.length)];
      sparkle.classList.add("is-flare");
      setTimeout(() => {
        sparkle.classList.remove("is-flare");
      }, 180 + Math.random() * 320);
    }, 130);
  }

  let pointerTargetX = 0;
  let pointerTargetY = 0;
  let pointerX = 0;
  let pointerY = 0;

  const updatePointerTarget = (clientX, clientY) => {
    const xPercent = clientX / Math.max(window.innerWidth, 1) - 0.5;
    const yPercent = clientY / Math.max(window.innerHeight, 1) - 0.5;
    pointerTargetX = -xPercent * 20;
    pointerTargetY = -yPercent * 14;
  };

  window.addEventListener("mousemove", (event) => {
    updatePointerTarget(event.clientX, event.clientY);
  });

  window.addEventListener(
    "touchmove",
    (event) => {
      const touch = event.touches && event.touches[0];
      if (!touch) return;
      updatePointerTarget(touch.clientX, touch.clientY);
    },
    { passive: true }
  );

  window.addEventListener("mouseleave", () => {
    pointerTargetX = 0;
    pointerTargetY = 0;
  });

  let tick = 0;
  const animateCosmos = () => {
    tick += 1;
    const x = (tick * 0.03) % 360;
    const y = (tick * 0.012) % 260;
    starsLayer.style.backgroundPosition = `${x}px ${y}px`;

    pointerX += (pointerTargetX - pointerX) * 0.07;
    pointerY += (pointerTargetY - pointerY) * 0.07;

    starsLayer.style.transform = `translate3d(${(pointerX * 1.35).toFixed(2)}px, ${(pointerY * 1.1).toFixed(2)}px, 0)`;
    if (sparklesLayer) {
      sparklesLayer.style.transform = `translate3d(${(pointerX * 1.75).toFixed(2)}px, ${(pointerY * 1.45).toFixed(2)}px, 0)`;
    }
    if (orbitsLayer) {
      orbitsLayer.style.transform = `translate3d(${(pointerX * 1.95).toFixed(2)}px, ${(pointerY * 1.6).toFixed(2)}px, 0)`;
    }
    if (navBar) {
      navBar.style.transform = `translate3d(${(pointerX * 0.16).toFixed(2)}px, ${(pointerY * 0.12).toFixed(2)}px, 0)`;
    }
    if (mainContainer) {
      mainContainer.style.transform = `translate3d(${(pointerX * 0.32).toFixed(2)}px, ${(pointerY * 0.24).toFixed(2)}px, 0)`;
    }

    requestAnimationFrame(animateCosmos);
  };

  requestAnimationFrame(animateCosmos);
});
