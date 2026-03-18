document.addEventListener("DOMContentLoaded", () => {
	const cards = document.querySelectorAll(".reveal-on-load");

	cards.forEach((card, index) => {
		const delay = 180 + index * 140;
		setTimeout(() => {
			card.classList.add("is-visible");
		}, delay);
	});

	const starsLayer = document.querySelector(".space-stars");
	if (!starsLayer) {
		return;
	}

	let tick = 0;
	const driftStars = () => {
		tick += 1;
		const x = (tick * 0.03) % 340;
		const y = (tick * 0.012) % 240;
		starsLayer.style.backgroundPosition = `${x}px ${y}px`;
		requestAnimationFrame(driftStars);
	};

	requestAnimationFrame(driftStars);
});
