document.addEventListener("DOMContentLoaded", () => {
	const postStatus = document.querySelector("#post-status");
	const postContainer = document.querySelector("#post-container");
	const commentsSection = document.querySelector("#comments-section");
	const commentsStatus = document.querySelector("#comments-status");
	const commentsContainer = document.querySelector("#comments-container");
	const addCommentLink = document.querySelector("#add-comment-link");
	let isAdmin = false;

	const params = new URLSearchParams(window.location.search);
	const postId = params.get("post_id");

	const escapeHtml = (value) => {
		return String(value)
			.replaceAll("&", "&amp;")
			.replaceAll("<", "&lt;")
			.replaceAll(">", "&gt;")
			.replaceAll('"', "&quot;")
			.replaceAll("'", "&#39;");
	};

	const initialsFromAuthor = (author) => {
		const parts = String(author).trim().split(/\s+/).filter(Boolean);
		if (parts.length === 0) return "??";
		if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
		return `${parts[0][0] || ""}${parts[1][0] || ""}`.toUpperCase();
	};

	const relativeTimeFromDate = (dateValue) => {
		const ms = new Date(dateValue).getTime();
		if (Number.isNaN(ms)) return "just now";
		const deltaSec = Math.max(0, Math.floor((Date.now() - ms) / 1000));
		if (deltaSec < 60) return `${deltaSec}s`;
		if (deltaSec < 3600) return `${Math.floor(deltaSec / 60)}m`;
		if (deltaSec < 86400) return `${Math.floor(deltaSec / 3600)}h`;
		return `${Math.floor(deltaSec / 86400)}d`;
	};

	const resolveApiUrl = (path) => {
		if (window.location.protocol === "file:") return null;
		return new URL(path, window.location.href).toString();
	};

	const fetchViewer = async () => {
		const url = resolveApiUrl("api/auth/get-user.php");
		if (!url) return null;
		try {
			const resp = await fetch(url, { credentials: "same-origin" });
			const data = await resp.json();
			return data?.authenticated ? data.user : null;
		} catch {
			return null;
		}
	};

	const revealCards = () => {
		document.querySelectorAll(".reveal-on-load").forEach((card, i) => {
			setTimeout(() => card.classList.add("is-visible"), 180 + i * 100);
		});
	};

	// Star drift animation
	const starsLayer = document.querySelector(".space-stars");
	if (starsLayer) {
		let tick = 0;
		const drift = () => {
			tick++;
			starsLayer.style.backgroundPosition = `${(tick * 0.03) % 340}px ${(tick * 0.012) % 240}px`;
			requestAnimationFrame(drift);
		};
		requestAnimationFrame(drift);
	}

	if (!postId) {
		postStatus.textContent = "No post specified.";
		return;
	}

	// Set the Add Comment link
	if (addCommentLink) {
		addCommentLink.href = `create-comment.html?post_id=${encodeURIComponent(postId)}`;
	}

	const fetchPost = async () => {
		const url = resolveApiUrl(`api/blog/get-post.php?post_id=${encodeURIComponent(postId)}`);
		if (!url) {
			postStatus.textContent = "Start a local PHP server (php -S localhost:8000) and open via http://localhost:8000.";
			return;
		}

		try {
			const response = await fetch(url);
			const data = await response.json();

			if (!response.ok || !data.post) {
				postStatus.textContent = data.error || "Post not found.";
				return;
			}

			const post = data.post;
			const author = post.author || "Unknown";
			const title = post.title || "Untitled";
			const body = post.body_text || "";
			const postedAt = relativeTimeFromDate(post.created_at);
			const initials = initialsFromAuthor(author);

			postStatus.textContent = "";
			document.title = `${title} | Wafiq's Blog`;

			postContainer.innerHTML = `
				<section class="tweet-card p-3 p-md-4 mb-4 reveal-on-load">
					<div class="d-flex gap-3">
						<div class="avatar-dot">${escapeHtml(initials)}</div>
						<div class="w-100">
							<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
								<h1 class="h4 fw-bold mb-0">${escapeHtml(title)}</h1>
							</div>
							<div class="d-flex gap-2 mb-2">
								<span class="text-secondary">@${escapeHtml(author)}</span>
								<span class="text-secondary">&middot;</span>
								<span class="text-secondary">${escapeHtml(postedAt)}</span>
							</div>
							<p class="mb-0 post-body">${escapeHtml(body)}</p>
							<div class="mt-3" style="${isAdmin ? "" : "display:none;"}">
								<button id="delete-post-button" class="btn btn-outline-danger rounded-pill px-3 fw-semibold" type="button">
									Delete Post
								</button>
							</div>
						</div>
					</div>
				</section>`;

			commentsSection.classList.remove("d-none");
			revealCards();
			fetchComments();
		} catch (err) {
			postStatus.textContent = "Could not load post. Make sure the PHP server is running.";
		}
	};

	const wireDelete = () => {
		postContainer.addEventListener("click", async (event) => {
			const target = event.target instanceof HTMLElement ? event.target : null;
			const button = target ? target.closest("#delete-post-button") : null;
			if (!button) return;
			if (!isAdmin) return;

			const deleteUrl = resolveApiUrl("api/blog/delete-post.php");
			if (!deleteUrl) return;

			try {
				const response = await fetch(deleteUrl, {
					method: "POST",
					credentials: "same-origin",
					headers: { "Content-Type": "application/json" },
					body: JSON.stringify({ post_id: Number(postId) }),
				});
				const data = await response.json().catch(() => ({}));
				if (!response.ok) {
					throw new Error(data.error || "Failed to delete post");
				}
				window.location.href = "index.html";
			} catch (error) {
				alert(error.message || "Could not delete post.");
			}
		});
	};

	const fetchComments = async () => {
		const url = resolveApiUrl(`api/blog/get-comments.php?post_id=${encodeURIComponent(postId)}`);
		if (!url) return;

		try {
			const response = await fetch(url);
			const data = await response.json();
			const comments = Array.isArray(data.comments) ? data.comments : [];

			if (comments.length === 0) {
				commentsStatus.textContent = "No comments yet. Be the first!";
				commentsContainer.innerHTML = "";
				return;
			}

			commentsStatus.textContent = `${comments.length} comment${comments.length !== 1 ? "s" : ""}`;
			commentsContainer.innerHTML = comments.map((comment) => {
				const author = comment.author || "Unknown";
				const initials = initialsFromAuthor(author);
				const postedAt = relativeTimeFromDate(comment.created_at);
				return `
					<div class="tweet-card p-3 mb-2 reveal-on-load">
						<div class="d-flex gap-3">
							<div class="avatar-dot" style="width:36px;height:36px;font-size:0.75rem;">${escapeHtml(initials)}</div>
							<div class="w-100">
								<div class="d-flex gap-2 mb-1">
									<strong>@${escapeHtml(author)}</strong>
									<span class="text-secondary">&middot;</span>
									<span class="text-secondary">${escapeHtml(postedAt)}</span>
								</div>
								<p class="mb-0 text-secondary">${escapeHtml(comment.content)}</p>
							</div>
						</div>
					</div>`;
			}).join("");

			revealCards();
		} catch (err) {
			commentsStatus.textContent = "Could not load comments.";
		}
	};

	(async () => {
		const viewer = await fetchViewer();
		isAdmin = Boolean(viewer && viewer.role === "admin");
		wireDelete();
		await fetchPost();
	})();
});