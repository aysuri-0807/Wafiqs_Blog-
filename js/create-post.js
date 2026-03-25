document.addEventListener("DOMContentLoaded", () => {
	const form = document.querySelector("#create-post-form");
	const statusNode = document.querySelector("#create-status");
	const userIdInput = document.querySelector("#user-id");
	const titleInput = document.querySelector("#post-title");
	const contentInput = document.querySelector("#post-content");

	const starsLayer = document.querySelector(".space-stars");
	if (starsLayer) {
		let tick = 0;
		const driftStars = () => {
			tick += 1;
			const x = (tick * 0.03) % 340;
			const y = (tick * 0.012) % 240;
			starsLayer.style.backgroundPosition = `${x}px ${y}px`;
			requestAnimationFrame(driftStars);
		};
		requestAnimationFrame(driftStars);
	}

	const card = document.querySelector(".reveal-on-load");
	if (card) {
		setTimeout(() => {
			card.classList.add("is-visible");
		}, 180);
	}

	if (!form || !statusNode || !userIdInput || !titleInput || !contentInput) {
		return;
	}

	const setStatus = (message, tone = "muted") => {
		statusNode.textContent = message;
		if (tone === "error") {
			statusNode.style.color = "#ffb3bd";
			return;
		}
		if (tone === "success") {
			statusNode.style.color = "#9bf0c0";
			return;
		}
		statusNode.style.color = "";
	};

	const resolveApiUrl = (path) => {
		if (window.location.protocol === "file:") {
			return null;
		}

		return new URL(path, window.location.href).toString();
	};

	form.addEventListener("submit", async (event) => {
		event.preventDefault();

		const userId = Number(userIdInput.value);
		const title = titleInput.value.trim();
		const contentText = contentInput.value.trim();

		if (!Number.isInteger(userId) || userId <= 0) {
			setStatus("Enter a valid user ID.", "error");
			return;
		}
		if (!title || !contentText) {
			setStatus("Title and content are required.", "error");
			return;
		}

		setStatus("Publishing post...");

		const contentJson = {
			blocks: [
				{
					type: "text",
					data: {
						text: contentText,
					},
				},
			],
			comments: [],
		};

		const createPostApiUrl = resolveApiUrl("api/blog/create-post.php");
		if (!createPostApiUrl) {
			setStatus("Start a local PHP server (for example: php -S localhost:8000), then open this page through http://localhost:8000.", "error");
			return;
		}

		try {
			const response = await fetch(createPostApiUrl, {
				method: "POST",
				headers: {
					"Content-Type": "application/json",
				},
				body: JSON.stringify({
					user_id: userId,
					title,
					content_json: contentJson,
				}),
			});

			const rawBody = await response.text();
			let payload = null;
			try {
				payload = rawBody ? JSON.parse(rawBody) : null;
			} catch {
				payload = null;
			}

			if (!response.ok) {
				const apiMessage =
					(payload && payload.error)
						|| rawBody
						|| "Unable to publish post";
				throw new Error(apiMessage);
			}

			setStatus("Post published. Redirecting to home feed...", "success");
			form.reset();
			setTimeout(() => {
				window.location.href = "index.html";
			}, 900);
		} catch (error) {
			setStatus(error.message || "Something went wrong.", "error");
		}
	});
});
