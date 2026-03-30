document.addEventListener("DOMContentLoaded", () => {
	const commentForm = document.querySelector("#create-comment-form");
	const statusNode = document.querySelector("#comment-status");
	const postIdInput = document.querySelector("#comment-post-id");
	const contentInput = document.querySelector("#comment-content");

	const card = document.querySelector(".reveal-on-load");
	if (card) {
		setTimeout(() => {
			card.classList.add("is-visible");
		}, 180);
	}
	const params = new URLSearchParams(window.location.search);
	const postIdFromUrl = params.get("post_id");
	if (postIdFromUrl && postIdInput) {
		postIdInput.value = postIdFromUrl;
		postIdInput.readOnly = true; // prevent user from changing it
	}

	if (!commentForm || !statusNode || !postIdInput || !contentInput) {
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

	const requireLoggedIn = async () => {
		const getUserUrl = resolveApiUrl("api/auth/get-user.php");
		if (!getUserUrl) return;
		try {
			const resp = await fetch(getUserUrl, { credentials: "same-origin" });
			const data = await resp.json();
			if (!data?.authenticated) {
				window.location.href = "login.php";
			}
		} catch {
			// If session check fails, still allow API to enforce auth.
		}
	};

	requireLoggedIn();

	commentForm.addEventListener("submit", async (event) => {
		event.preventDefault();

		const postId = Number(postIdInput.value);
		const contentText = contentInput.value.trim();

		if (!Number.isInteger(postId) || postId <= 0) {
			setStatus("Enter a valid post ID.", "error");
			return;
		}
		if (!contentText) {
			setStatus("Comment content is required.", "error");
			return;
		}

		setStatus("Posting comment...");

		const createCommentApiUrl = resolveApiUrl("api/blog/create-comment.php");
		if (!createCommentApiUrl) {
			setStatus("Start a local PHP server (for example: php -S localhost:8000), then open this page through http://localhost:8000.", "error");
			return;
		}

		try {
			const response = await fetch(createCommentApiUrl, {
				method: "POST",
				headers: {
					"Content-Type": "application/json",
				},
				body: JSON.stringify({
					post_id: postId,
					content: contentText,
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
					|| "Unable to post comment";
				throw new Error(apiMessage);
			}

			setStatus("Comment posted successfully! Redirecting...", "success");
			setTimeout(() => {
				window.location.href = `post.html?post_id=${postId}`;
			}, 900);
		} catch (error) {
			setStatus(error.message || "Something went wrong.", "error");
		}
	});
});