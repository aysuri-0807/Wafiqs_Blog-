(() => {
	const resolveApiUrl = (path) => {
		if (window.location.protocol === "file:") {
			return null;
		}

		return new URL(path, window.location.href).toString();
	};

	const checkAdminStatus = async () => {
		const getUserUrl = resolveApiUrl("api/auth/get-user.php");
		if (!getUserUrl) {
			return;
		}
		try {
			const userResp = await fetch(getUserUrl, { credentials: "same-origin" });
			const userData = await userResp.json();
			const user = userData?.authenticated ? userData.user : null;
			if (!user) {
				window.location.href = "login.php";
				return;
			}
			if (user.role !== "admin") {
				window.location.href = "index.html";
			}
		} catch (_e) {
			console.warn("Could not verify user session for publish permissions.");
		}
	};

	checkAdminStatus();

	document.addEventListener("DOMContentLoaded", () => {
		/** @type {HTMLFormElement | null} */
		const form = document.querySelector("#create-post-form");
		/** @type {HTMLElement | null} */
		const statusNode = document.querySelector("#create-status");
		/** @type {HTMLInputElement | null} */
		const titleInput = document.querySelector("#post-title");
		/** @type {HTMLTextAreaElement | null} */
		const contentInput = document.querySelector("#post-content");

		/** @type {HTMLElement | null} */
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

		if (!form || !statusNode || !titleInput || !contentInput) {
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

		form.addEventListener("submit", async (event) => {
			event.preventDefault();

			const title = titleInput.value.trim();
			const contentText = contentInput.value.trim();

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
				setStatus(
					"Start a local PHP server (for example: php -S localhost:8000), then open this page through http://localhost:8000.",
					"error",
				);
				return;
			}

			try {
				const response = await fetch(createPostApiUrl, {
					method: "POST",
					headers: {
						"Content-Type": "application/json",
					},
					credentials: "same-origin",
					body: JSON.stringify({
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
						(payload && payload.error) || rawBody || "Unable to publish post";
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
})();
