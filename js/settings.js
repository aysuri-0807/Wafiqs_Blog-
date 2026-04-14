document.addEventListener("DOMContentLoaded", () => {
	const profileForm = document.querySelector("#profile-form");
	const passwordForm = document.querySelector("#password-form");
	const logoutButton = document.querySelector("#logout-button");
	const statusNode = document.querySelector("#settings-status");

	const usernameInput = document.querySelector("#profile-username");
	const emailInput = document.querySelector("#profile-email");
	const showEmailInput = document.querySelector("#profile-show-email");

	if (!profileForm || !passwordForm || !statusNode) {
		return;
	}

	const resolveApiUrl = (path) => {
		if (window.location.protocol === "file:") {
			return null;
		}
		return new URL(path, window.location.href).toString();
	};

	const setStatus = (message, kind = "info") => {
		statusNode.textContent = message;
		statusNode.className = "compose-status mb-3";
		if (kind === "error") {
			statusNode.classList.add("status-error");
		} else if (kind === "success") {
			statusNode.classList.add("status-success");
		}
	};

	const postJson = async (url, payload) => {
		const response = await fetch(url, {
			method: "POST",
			headers: {
				"Content-Type": "application/json",
			},
			credentials: "same-origin",
			body: JSON.stringify(payload),
		});
		const data = await response.json().catch(() => ({}));
		if (!response.ok) {
			throw new Error(data.error || "Request failed");
		}
		return data;
	};

	const loadUserProfile = async () => {
		const getUserUrl = resolveApiUrl("api/auth/get-user.php");
		if (!getUserUrl) {
			setStatus("PHP server not running", "error");
			return;
		}

		try {
			const response = await fetch(getUserUrl, {
				credentials: "same-origin",
			});
			const data = await response.json();

			if (!data.authenticated) {
				window.location.href = "login.php";
				return;
			}

			const user = data.user;
			if (usernameInput) usernameInput.value = user.username || "";
			if (emailInput) emailInput.value = user.email || "";
			if (showEmailInput) showEmailInput.checked = user.show_email || false;
		} catch (error) {
			setStatus("Failed to load profile data", "error");
		}
	};

	profileForm.addEventListener("submit", async (event) => {
		event.preventDefault();
		setStatus("Updating profile...");

		const updateApiUrl = resolveApiUrl("api/auth/update-user.php");
		if (!updateApiUrl) {
			setStatus("PHP server not running", "error");
			return;
		}

		try {
			const data = await postJson(updateApiUrl, {
				action: "update_profile",
				username: usernameInput.value.trim(),
				email: emailInput.value.trim(),
				show_email: showEmailInput.checked,
			});
			setStatus(data.message || "Profile updated successfully!", "success");
		} catch (error) {
			setStatus(error.message || "Profile update failed", "error");
		}
	});

	passwordForm.addEventListener("submit", async (event) => {
		event.preventDefault();

		const currentPassword = document.querySelector("#current-password").value;
		const newPassword = document.querySelector("#new-password").value;
		const confirmPassword = document.querySelector("#confirm-password").value;

		if (newPassword !== confirmPassword) {
			setStatus("New passwords do not match", "error");
			return;
		}

		setStatus("Changing password...");

		const updateApiUrl = resolveApiUrl("api/auth/update-user.php");
		if (!updateApiUrl) {
			setStatus("PHP server not running", "error");
			return;
		}

		try {
			const data = await postJson(updateApiUrl, {
				action: "change_password",
				current_password: currentPassword,
				new_password: newPassword,
				confirm_password: confirmPassword,
			});
			setStatus(data.message || "Password changed successfully!", "success");
			passwordForm.reset();
		} catch (error) {
			setStatus(error.message || "Password change failed", "error");
		}
	});

	if (logoutButton) {
		logoutButton.addEventListener("click", async () => {
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
	}

	loadUserProfile();
});
