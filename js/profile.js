document.addEventListener("DOMContentLoaded", () => {
	const profileUsername = document.querySelector("#profile-username");
	const profileEmail = document.querySelector("#profile-email");
	const profileStatus = document.querySelector("#profile-status");

	const params = new URLSearchParams(window.location.search);
	const username = params.get("username");

	const resolveApiUrl = (path) => {
		if (window.location.protocol === "file:") {
			return null;
		}
		return new URL(path, window.location.href).toString();
	};

	if (!username) {
		profileStatus.textContent = "No user specified.";
		return;
	}

	const fetchCurrentUser = async () => {
		const getUserUrl = resolveApiUrl("api/auth/get-user.php");
		if (!getUserUrl) return null;

		try {
			const response = await fetch(getUserUrl, {
				credentials: "same-origin",
			});
			const data = await response.json();
			return data?.authenticated ? data.user : null;
		} catch {
			return null;
		}
	};

	const fetchUserProfile = async () => {
		const profileUrl = resolveApiUrl(`api/auth/get-profile.php?username=${encodeURIComponent(username)}`);
		if (!profileUrl) {
			profileStatus.textContent = "PHP server not running";
			return;
		}

		try {
			const [currentUser, profileResponse] = await Promise.all([
				fetchCurrentUser(),
				fetch(profileUrl),
			]);

			const data = await profileResponse.json();

			if (!profileResponse.ok || !data.user) {
				profileStatus.textContent = data.error || "User not found";
				return;
			}

			const user = data.user;
			profileUsername.textContent = `@${user.username}`;
			document.title = `${user.username} | Wafiq's Blog`;

			// Show contact details only if viewing own profile
			const isOwnProfile = currentUser && currentUser.username.toLowerCase() === user.username.toLowerCase();
			if (isOwnProfile) {
				const userEmail = currentUser.email || "(No email provided)";
				if (currentUser.show_email) {
					profileEmail.textContent = `Contact me at ${userEmail}`;
				} else {
					profileEmail.textContent = `Hidden From Users: ${userEmail}`;
				}
			} else if (user.email) {
				profileEmail.textContent = `Contact me at ${user.email}`;
			} else {
				profileEmail.textContent = "(Contact Info Hidden)";
			}

			profileStatus.textContent = "";
			profileStatus.style.display = "none";
		} catch (error) {
			profileStatus.textContent = "Could not load user profile.";
		}
	};

	fetchUserProfile();
});
