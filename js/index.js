const resolveApiUrl = (path) => {
  if (window.location.protocol === "file:") {
    return null;
  }

  return new URL(path, window.location.href).toString();
};

document.addEventListener("DOMContentLoaded", () => {
  const feedNode = document.querySelector("#post-feed");
  const statusNode = document.querySelector("#feed-status");
  const feedAlertNode = document.querySelector("#feed-alert");
  const postSortSelect = document.getElementById("post-sort");
  const SORT_STORAGE_KEY = "homepagePostSort";
  const COMMENT_DELETE_QUEUE_KEY = "pendingCommentDeletes";
  const VALID_SORTS = new Set([
    "newest",
    "most-liked",
    "most-controversial",
    "trending",
  ]);
  let allPosts = [];
  let currentUser = null;

  const setFeedAlert = (message) => {
    if (!feedAlertNode) return;
    feedAlertNode.textContent = message;
  };

  const clearFeedAlert = () => {
    if (!feedAlertNode) return;
    feedAlertNode.textContent = "";
  };

  const showSortBar = () => {
    statusNode?.classList.remove("d-none");
  };

  const hideSortBar = () => {
    statusNode?.classList.add("d-none");
  };

  const loadSavedSort = () => {
    try {
      const savedSort = window.localStorage.getItem(SORT_STORAGE_KEY) || "";
      if (VALID_SORTS.has(savedSort)) {
        return savedSort;
      }
    } catch (_error) {
      // Continue with default sort if storage is unavailable.
    }
    return "newest";
  };

  const saveSort = (sortValue) => {
    if (!VALID_SORTS.has(sortValue)) return;
    try {
      window.localStorage.setItem(SORT_STORAGE_KEY, sortValue);
    } catch (_error) {
      // Ignore storage failures so sorting still works in memory.
    }
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

  const sortedPosts = (posts, sortBy) => {
    const copy = [...posts];
    const numeric = (value) => Number(value || 0);
    const timestamp = (value) => new Date(value || 0).getTime() || 0;

    switch (sortBy) {
      case "most-liked":
        return copy.sort((a, b) => numeric(b.likes) - numeric(a.likes));
      case "most-controversial":
        return copy.sort((a, b) => numeric(b.dislikes) - numeric(a.dislikes));
      case "trending":
        return copy.sort(
          (a, b) => numeric(b.comment_count) - numeric(a.comment_count),
        );
      case "newest":
      default:
        return copy.sort(
          (a, b) => timestamp(b.created_at) - timestamp(a.created_at),
        );
    }
  };

  const readPendingCommentDeletes = () => {
    try {
      const rawValue = window.localStorage.getItem(COMMENT_DELETE_QUEUE_KEY);
      const parsedValue = rawValue ? JSON.parse(rawValue) : [];
      return Array.isArray(parsedValue) ? parsedValue : [];
    } catch (_error) {
      return [];
    }
  };

  const clearPendingCommentDeletes = () => {
    try {
      window.localStorage.removeItem(COMMENT_DELETE_QUEUE_KEY);
    } catch (_error) {
      // Ignore storage failures so the feed still renders.
    }
  };

  const applyPendingCommentDeletes = (posts) => {
    const pendingDeletes = readPendingCommentDeletes();
    if (pendingDeletes.length === 0) return posts;

    const deleteCountsByPostId = new Map();
    pendingDeletes.forEach((entry) => {
      const postId = Number(entry && entry.postId);
      if (!Number.isInteger(postId) || postId <= 0) return;
      deleteCountsByPostId.set(
        postId,
        (deleteCountsByPostId.get(postId) || 0) + 1,
      );
    });

    if (deleteCountsByPostId.size === 0) {
      clearPendingCommentDeletes();
      return posts;
    }

    const updatedPosts = posts.map((post) => {
      const postId = Number(post.post_id);
      const deleteCount = deleteCountsByPostId.get(postId) || 0;
      if (deleteCount <= 0) return post;

      return {
        ...post,
        comment_count: Math.max(
          0,
          Number(post.comment_count || 0) - deleteCount,
        ),
      };
    });

    clearPendingCommentDeletes();
    return updatedPosts;
  };

  const renderPosts = (posts, user, { animate = false } = {}) => {
    if (!feedNode) return;

    if (posts.length === 0) {
      feedNode.innerHTML = "";
      return;
    }

    feedNode.innerHTML = posts
      .map((post) => {
        const author = post.author || "Unknown";
        const title = post.title || "Untitled";
        const body = post.body_text || "";
        const likes = Number(post.likes || 0);
        const dislikes = Number(post.dislikes || 0);
        const commentCount = Number(post.comment_count || 0);
        const userVote =
          post.user_vote === "like" || post.user_vote === "dislike"
            ? post.user_vote
            : null;
        const postUrl = `post.html?post_id=${escapeHtml(post.post_id)}`;
        const profileUrl = `profile.html?username=${encodeURIComponent(author)}`;
        const canDelete = user && Number(user.id) === Number(post.author_id);
        const deleteAction = canDelete
          ? `<span class="text-danger vote-chip delete-post-btn" data-id="${escapeHtml(post.post_id)}">Delete</span>`
          : "";
        const postedAt = relativeTimeFromDate(post.created_at);
        const initials = initialsFromAuthor(author);

        return `
          <section class="tweet-card p-3 p-md-4 mb-3 ${animate ? "reveal-on-load" : ""} open-post-card" data-post-url="${postUrl}" role="button" tabindex="0" aria-label="Open post ${escapeHtml(title)}">
            <div class="d-flex gap-3">
              <div class="avatar-dot">${escapeHtml(initials)}</div>
              <div class="w-100">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                  <a href="${postUrl}" class="text-decoration-none open-post-link" style="color:inherit;"><strong>${escapeHtml(title)}</strong></a>
                  <a href="${profileUrl}" class="author-link">@${escapeHtml(author)}</a>
                  <span class="text-secondary">&middot;</span>
                  <span class="text-secondary">${escapeHtml(postedAt)}</span>
                </div>
                <p class="mb-2 text-secondary post-body">${escapeHtml(body)}</p>
                <div class="d-flex gap-3 text-secondary small post-actions">
                  <a href="${postUrl}" class="text-decoration-none text-secondary open-post-link">Comments (${escapeHtml(commentCount)})</a>
                  <button type="button" class="post-vote-btn vote-chip vote-like ${userVote === "like" ? "is-active" : ""}" data-id="${escapeHtml(post.post_id)}" data-vote-type="like">Like <span data-role="post-likes">${escapeHtml(likes)}</span></button>
                  <button type="button" class="post-vote-btn vote-chip vote-dislike ${userVote === "dislike" ? "is-active" : ""}" data-id="${escapeHtml(post.post_id)}" data-vote-type="dislike">Dislike <span data-role="post-dislikes">${escapeHtml(dislikes)}</span></button>
                  ${deleteAction}
                </div>
              </div>
            </div>
          </section>`;
      })
      .join("");

    if (animate) {
      renderCardsWithReveal();
    }
  };

  const applySortAndRender = (user, { animate = false } = {}) => {
    const selectedSort = postSortSelect?.value || "newest";
    const orderedPosts = sortedPosts(allPosts, selectedSort);
    renderPosts(orderedPosts, user, { animate });
  };

  const fetchAndRenderPosts = async () => {
    if (!feedNode) return;

    const getUserUrl = resolveApiUrl("api/auth/get-user.php");
    let user = null;
    try {
      if (getUserUrl) {
        const userResp = await fetch(getUserUrl, {
          credentials: "same-origin",
        });
        const userData = await userResp.json();
        user = userData?.authenticated ? userData.user : null;
      }
    } catch (_error) {
      console.warn("Could not verify user session for delete permissions.");
    }

    const postsApiUrl = resolveApiUrl("api/blog/get-posts.php");
    if (!postsApiUrl) {
      hideSortBar();
      setFeedAlert(
        "Start a local PHP server (for example: php -S localhost:8000), then open this page through http://localhost:8000.",
      );
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
      allPosts = applyPendingCommentDeletes(posts);

      if (posts.length === 0) {
        hideSortBar();
        setFeedAlert("No posts yet.");
        feedNode.innerHTML = "";
        return;
      }

      feedNode.addEventListener("click", async (event) => {
        const voteBtn = event.target.closest(".post-vote-btn");
        if (voteBtn) {
          const postId = Number(voteBtn.dataset.id);
          const voteType = voteBtn.dataset.voteType;
          const card = voteBtn.closest(".tweet-card");

          if (
            !Number.isInteger(postId) ||
            postId <= 0 ||
            (voteType !== "like" && voteType !== "dislike") ||
            !card
          ) {
            return;
          }

          if (!user) {
            window.location.href = "login.php";
            return;
          }

          const voteApiUrl = resolveApiUrl("api/blog/vote-post.php");
          if (!voteApiUrl) {
            alert("Could not reach vote API.");
            return;
          }

          voteBtn.disabled = true;
          try {
            const voteResponse = await fetch(voteApiUrl, {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              credentials: "same-origin",
              body: JSON.stringify({
                post_id: postId,
                vote_type: voteType,
              }),
            });

            const payload = await voteResponse.json().catch(() => null);
            if (!voteResponse.ok || !payload) {
              throw new Error(
                (payload && payload.error) || "Could not save vote",
              );
            }

            const likesNode = card.querySelector("[data-role='post-likes']");
            const dislikesNode = card.querySelector(
              "[data-role='post-dislikes']",
            );
            if (likesNode)
              likesNode.textContent = String(Number(payload.likes || 0));
            if (dislikesNode)
              dislikesNode.textContent = String(Number(payload.dislikes || 0));

            card.querySelectorAll(".post-vote-btn").forEach((button) => {
              const selected = button.dataset.voteType === payload.user_vote;
              button.classList.toggle("is-active", Boolean(selected));
            });
          } catch (error) {
            alert(error.message || "Could not save vote");
          } finally {
            voteBtn.disabled = false;
          }

          return;
        }

        const btn = event.target.closest(".delete-post-btn");
        if (btn) {
          const postId = btn.dataset.id;
          const card = btn.closest(".tweet-card");

          const modalEl = document.getElementById("deleteConfirmModal");
          const confirmBtn = document.getElementById("confirmDeleteBtn");
          const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

          // Clone button to remove any previous listener
          const newConfirmBtn = confirmBtn.cloneNode(true);
          confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

          newConfirmBtn.addEventListener(
            "click",
            async () => {
              newConfirmBtn.disabled = true;
              newConfirmBtn.textContent = "Deleting\u2026";
              try {
                const deleteResponse = await fetch("api/blog/delete-post.php", {
                  method: "POST",
                  credentials: "same-origin",
                  headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({ post_id: Number(postId) }),
                });
                if (!deleteResponse.ok)
                  throw new Error("Failed to delete post");
                modal.hide();
                if (card) {
                  card.classList.add("is-deleting");
                  setTimeout(() => {
                    card.remove();
                  }, 400);
                }
              } catch (error) {
                console.error(error);
                modal.hide();
                alert("Could not delete post.");
              }
            },
            { once: true },
          );

          modal.show();
          return;
        }

        const postCard = event.target.closest(".open-post-card");
        if (!postCard) return;

        const postUrl = postCard.dataset.postUrl;
        if (!postUrl) return;

        event.preventDefault();
        window.location.href = postUrl;
      });
      showSortBar();
      clearFeedAlert();

      if (postSortSelect) {
        postSortSelect.addEventListener("change", () => {
          saveSort(postSortSelect.value);
          applySortAndRender(currentUser);
        });
      }

      currentUser = user;
      applySortAndRender(currentUser, { animate: true });
    } catch (_error) {
      hideSortBar();
      setFeedAlert(
        "Could not load posts right now. Make sure the PHP server is running and reachable.",
      );
      feedNode.innerHTML = "";
    }
  };

  if (postSortSelect) {
    postSortSelect.value = loadSavedSort();
  }

  window.addEventListener("storage", (event) => {
    if (event.key !== COMMENT_DELETE_QUEUE_KEY || !event.newValue) return;
    if (!allPosts.length) return;

    allPosts = applyPendingCommentDeletes(allPosts);
    applySortAndRender(currentUser, { animate: false });
  });

  window.addEventListener("pageshow", () => {
    if (!allPosts.length) return;

    const updatedPosts = applyPendingCommentDeletes(allPosts);
    if (updatedPosts === allPosts) return;

    allPosts = updatedPosts;
    applySortAndRender(currentUser, { animate: false });
  });

  fetchAndRenderPosts();
});
