document.addEventListener("DOMContentLoaded", () => {
  const root = document.body;
  if (!root) {
    return;
  }

  root.classList.add("cosmos-canvas-active");

  const canvas = document.createElement("canvas");
  canvas.className = "cosmos-canvas";
  canvas.setAttribute("aria-hidden", "true");
  root.prepend(canvas);

  const context = canvas.getContext("2d", { alpha: true, desynchronized: true });
  if (!context) {
    return;
  }

  let width = 0;
  let height = 0;
  let dpr = 1;
  let stars = [];
  let pointerX = 0;
  let pointerY = 0;
  let pointerTargetX = 0;
  let pointerTargetY = 0;
  let animationId = 0;
  let lastFrameTime = 0;
  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  const clampDpr = () => Math.min(window.devicePixelRatio || 1, 1.5);

  const randomStar = () => ({
    x: Math.random() * width,
    y: Math.random() * height,
    radius: 0.6 + Math.random() * 1.8,
    alpha: 0.3 + Math.random() * 0.65,
    twinkle: 0.5 + Math.random() * 1.7,
    speed: 0.015 + Math.random() * 0.07,
    drift: (Math.random() - 0.5) * 0.06,
    phase: Math.random() * Math.PI * 2,
  });

  const rebuildStars = () => {
    const count = Math.max(55, Math.floor((width * height) / 14000));
    stars = Array.from({ length: count }, randomStar);
  };

  const resize = () => {
    dpr = clampDpr();
    width = window.innerWidth;
    height = window.innerHeight;

    canvas.width = Math.max(1, Math.floor(width * dpr));
    canvas.height = Math.max(1, Math.floor(height * dpr));
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;

    context.setTransform(dpr, 0, 0, dpr, 0, 0);
    rebuildStars();
  };

  const draw = (time) => {
    context.clearRect(0, 0, width, height);

    pointerX += (pointerTargetX - pointerX) * 0.07;
    pointerY += (pointerTargetY - pointerY) * 0.07;

    for (let i = 0; i < stars.length; i += 1) {
      const star = stars[i];
      const twinkleFactor = 0.72 + Math.sin(time * 0.001 * star.twinkle + star.phase) * 0.28;
      const alpha = star.alpha * twinkleFactor;

      star.y += star.speed;
      star.x += star.drift;

      if (star.y > height + 3) {
        star.y = -3;
        star.x = Math.random() * width;
      }
      if (star.x < -3) {
        star.x = width + 3;
      }
      if (star.x > width + 3) {
        star.x = -3;
      }

      const px = star.x + pointerX * 3.8;
      const py = star.y + pointerY * 2.8;

      context.globalAlpha = alpha;
      context.fillStyle = "#eaf3ff";
      context.beginPath();
      context.arc(px, py, star.radius, 0, Math.PI * 2);
      context.fill();
    }

    context.globalAlpha = 1;
  };

  const frame = (time) => {
    if (document.hidden) {
      animationId = window.requestAnimationFrame(frame);
      return;
    }

    if (time - lastFrameTime >= 33) {
      lastFrameTime = time;
      draw(time);
    }

    animationId = window.requestAnimationFrame(frame);
  };

  const updatePointerTarget = (clientX, clientY) => {
    pointerTargetX = (clientX / Math.max(width, 1) - 0.5) * -0.9;
    pointerTargetY = (clientY / Math.max(height, 1) - 0.5) * -0.7;
  };

  resize();
  draw(performance.now());

  window.addEventListener("resize", resize, { passive: true });
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

  if (!reduceMotion) {
    animationId = window.requestAnimationFrame(frame);
  }

  window.addEventListener("beforeunload", () => {
    if (animationId) {
      window.cancelAnimationFrame(animationId);
    }
  });
});
