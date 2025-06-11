function validateRegisterForm() {
    const pw = document.querySelector('input[name="password"]');
    const cpw = document.querySelector('input[name="confirm_password"]');
    if (pw.value !== cpw.value) {
        alert("Password dan konfirmasi password harus sama!");
        return false;
    }
    if (pw.value.length < 6) {
        alert("Password minimal 6 karakter!");
        return false;
    }
    return true;
}
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".form-komentar").forEach(form => {
    form.addEventListener("submit", async e => {
      e.preventDefault();
      const inp = form.querySelector(".input-komentar");
      const text = inp.value.trim();
      if (!text) return;

      const pid = form.dataset.postId;
      const box = document.getElementById("komentar-post-" + pid);

      const user = document.createElement("div");
      user.textContent = "Kamu: " + text;
      box.appendChild(user);
      inp.value = "";

      try {
        const res = await fetch("ai.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ message: text })
        });
        const data = await res.json();
        if (data.reply) {
          const aiEl = document.createElement("div");
          aiEl.innerHTML = `<strong style="color:#00FFFF;">Ngoura:</strong> ${data.reply}`;
          box.appendChild(aiEl);
        }
      } catch (err) {
        console.error(err);
      }
    });
  });
});


