const resolveApiUrl = (path) => {
  if (window.location.protocol === "file:") {
    return null;
  }

  return new URL(path, window.location.href).toString();
};

document.addEventListener("DOMContentLoaded", () => {
  const feedNode = document.querySelector("#post-feed");
  const statusNode = document.querySelector("#feed-status");
  const accountMenuButton = document.querySelector("#account-menu-button");
  const loginLinkButton = document.querySelector("#login-link-button");
  const logoutLink = document.querySelector("#logout-link");
  const createPostButton = document.getElementById("create-post-button");
  const updateAdminUi = (user) => {
    const isAdmin = Boolean(user && user.role === "admin");
    if (createPostButton) {
      createPostButton.style.visibility = isAdmin ? "visible" : "hidden";
    }

    document.querySelectorAll(".delete-btn").forEach((node) => {
      /** @type {HTMLElement} */ (node).style.display = isAdmin ? "inline" : "none";
      if (!isAdmin) {
        node.setAttribute("aria-hidden", "true");
      } else {
        node.removeAttribute("aria-hidden");
      }
    });
  };

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

  const renderCardsWithReveal = () => {
    const cards = document.querySelectorAll(".reveal-on-load");
    cards.forEach((card, index) => {
      const delay = 180 + index * 120;
      setTimeout(() => {
        card.classList.add("is-visible");
      }, delay);
    });
  };

  const fetchAndRenderPosts = async () => {
    if (!feedNode || !statusNode) return;

    const getUserUrl = resolveApiUrl("api/auth/get-user.php");
    let user = null;
    try {
      if (getUserUrl) {
        const userResp = await fetch(getUserUrl, { credentials: "same-origin" });
      const userData = await userResp.json();
      user = userData?.authenticated ? userData.user : null;
      }
    } catch (e) {
      console.warn("Could not verify user session for delete permissions.");
    }

    const postsApiUrl = resolveApiUrl("api/blog/get-posts.php");
    if (!postsApiUrl) {
      statusNode.textContent =
        "Start a local PHP server (for example: php -S localhost:8000), then open this page through http://localhost:8000.";
      feedNode.innerHTML = "";
      return;
    }

    try {
      const response = await fetch(postsApiUrl);
      if (!response.ok) {
        throw new Error(`Failed to load posts (${response.status})`);
      }

      const data = await response.json();
      const posts = Array.isArray(data.posts) ? data.posts : [];

      if (posts.length === 0) {
        statusNode.textContent = "No posts yet.";
        feedNode.innerHTML = "";
        return;
      }
      feedNode.addEventListener("click", async (event) => {
        const target = /** @type {HTMLElement | null} */ (event.target instanceof HTMLElement ? event.target : null);
        const btn = target ? target.closest(".delete-btn") : null;
        if (!btn) return;

        const postId = /** @type {HTMLElement} */ (btn).dataset.id;
        const deleteUrl = `api/blog/delete-post.php?id=${postId}`;

        try {
          const response = await fetch("api/blog/delete-post.php", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ post_id: Number(postId) }),
          });
          if (!response.ok) throw new Error("Failed to delete post");
          btn.closest(".tweet-card")?.remove();
        } catch (error) {
          console.error(error);
          alert("Could not delete post.");
        }
      });

      statusNode.textContent = "Latest posts";
      feedNode.innerHTML = posts
        .map((post) => {
          const author = post.author || "Unknown";
          const title = post.title || "Untitled";
          const body = post.body_text || "";
          const likes = Number(post.likes || 0);
          const dislikes = Number(post.dislikes || 0);
          const shares = Number(post.share_count || 0);
          const postedAt = relativeTimeFromDate(post.created_at);
          const initials = initialsFromAuthor(author);
          return `
					<section class="tweet-card p-3 p-md-4 mb-3 reveal-on-load">
						<div class="d-flex gap-3">
							<div class="avatar-dot">${escapeHtml(initials)}</div>
							<div class="w-100">
								<div class="d-flex flex-wrap align-items-center gap-2 mb-1">
									<a href="post.html?post_id=${escapeHtml(post.post_id)}" class="text-decoration-none" style="color:inherit;"><strong>${escapeHtml(title)}</strong></a>
									<span class="text-secondary">@${escapeHtml(author)}</span>
									<span class="text-secondary">&middot;</span>
									<span class="text-secondary">${escapeHtml(postedAt)}</span>
								</div>
								<p class="mb-2 text-secondary post-body">${escapeHtml(body)}</p>
								<div class="d-flex gap-3 text-secondary small post-actions">
									<a href="post.html?post_id=${escapeHtml(post.post_id)}" class="text-decoration-none text-secondary">Comment</a>
									<span>Like ${escapeHtml(likes)}</span>
									<span>Dislike ${escapeHtml(dislikes)}</span>
									<span class="text-danger cursor-pointer delete-btn" data-id="${escapeHtml(post.post_id)}" style="display:none;">Delete</span>
								</div>
							</div>
						</div>
					</section>`;
        })
        .join("");

      updateAdminUi(user);
      renderCardsWithReveal();
    } catch (error) {
      statusNode.textContent =
        "Could not load posts right now. Make sure the PHP server is running and reachable.";
      feedNode.innerHTML = "";
    }
  };

  const updateAuthUi = async () => {
    if (!accountMenuButton || !loginLinkButton || !logoutLink) return;
    const getUserUrl = resolveApiUrl("api/auth/get-user.php");
    if (!getUserUrl) return;

    try {
      const response = await fetch(getUserUrl, {
        credentials: "same-origin",
      });
      const data = await response.json();
      const user = data?.authenticated ? data.user : null;

      if (!user) {
        accountMenuButton.textContent = "Account";
        loginLinkButton.classList.remove("d-none");
        logoutLink.classList.add("disabled");
        logoutLink.setAttribute("aria-disabled", "true");
        updateAdminUi(null);
        return;
      }

      accountMenuButton.textContent = `@${user.username}`;
      loginLinkButton.classList.add("d-none");
      logoutLink.classList.remove("disabled");
      logoutLink.removeAttribute("aria-disabled");
      updateAdminUi(user);
    } catch (_error) {
      accountMenuButton.textContent = "Account";
    }
  };

  const wireLogout = () => {
    if (!logoutLink) return;
    logoutLink.addEventListener("click", async (event) => {
      event.preventDefault();
      if (logoutLink.classList.contains("disabled")) return;
      const logoutUrl = resolveApiUrl("api/auth/logout.php");
      if (!logoutUrl) {
        window.location.href = "login.php";
        return;
      }

      try {
        await fetch(logoutUrl, {
          method: "POST",
          credentials: "same-origin",
        });
      } finally {
        window.location.href = "login.php";
      }
    });
  };

  fetchAndRenderPosts();
  updateAuthUi();
  wireLogout();

  /** @type {HTMLElement | null} */
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
