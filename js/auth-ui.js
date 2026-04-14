//shared auth ui
// immediately invoked function - ugly but works
(function () {
	const resolveApiUrl = (path) => {
		if (window.location.protocol === "file:") {
			return null;
		}
		return new URL(path, window.location.href).toString();
	};

	const updateAdminUi = (user) => {
		const createPostButton = document.getElementById("create-post-button");
		if (!createPostButton) return;
		const isAdmin = Boolean(user && user.role === "admin");
		createPostButton.style.visibility = isAdmin ? "visible" : "hidden";
	};

	document.addEventListener("DOMContentLoaded", () => {
		const accountDropdown = document.querySelector("#account-dropdown");
		const accountMenuButton = document.querySelector("#account-menu-button");
		const loginLinkWrapper = document.querySelector("#login-link-wrapper");
		const myProfileLink = document.querySelector("#my-profile-link");
		const logoutLink = document.querySelector("#logout-link");

		if (!accountDropdown || !accountMenuButton || !loginLinkWrapper) {
			return;
		}

		const updateAuthUi = async () => {
			const getUserUrl = resolveApiUrl("api/auth/get-user.php");
			if (!getUserUrl) return;

			try {
				const response = await fetch(getUserUrl, {
					credentials: "same-origin",
				});
				const data = await response.json();
				const user = data?.authenticated ? data.user : null;

				if (!user) {
					accountDropdown.classList.add("d-none");
					loginLinkWrapper.classList.remove("d-none");
					if (logoutLink) {
						logoutLink.classList.add("disabled");
						logoutLink.setAttribute("aria-disabled", "true");
					}
					updateAdminUi(null);
					return;
				}

				accountDropdown.classList.remove("d-none");
				loginLinkWrapper.classList.add("d-none");
				accountMenuButton.textContent = `@${user.username}`;
				if (logoutLink) {
					logoutLink.classList.remove("disabled");
					logoutLink.removeAttribute("aria-disabled");
				}
				if (myProfileLink) {
					myProfileLink.classList.remove("d-none");
					myProfileLink.href = `profile.html?username=${encodeURIComponent(user.username)}`;
				}
				updateAdminUi(user);
			} catch (_error) {
				accountDropdown.classList.add("d-none");
				loginLinkWrapper.classList.remove("d-none");
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

		updateAuthUi();
		wireLogout();
	});
})();
