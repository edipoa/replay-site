/* Viana Clips — sample clip data (24h availability window) */
window.CLIPS = (function () {
  const thumbs = ["thumb-grass", "thumb-night", "thumb-grass thumb-chalk", "thumb-night thumb-chalk"];

  // "Now" anchor
  const NOW = new Date(2026, 4, 8, 23, 30, 0);
  // Clips spread across the last ~23h
  const offsets = [
    0.2, 0.8, 1.5, 2.3, 3.1, 4.4, 5.6, 6.8,
    8.1, 9.5, 10.9, 12.2, 13.6, 14.8, 16.1, 17.4,
    18.6, 19.8, 20.6, 21.3, 21.8, 22.4, 23.0, 23.6,
  ];

  const clips = [];
  for (let d = 0; d < 24; d++) {
    const hoursAgo = offsets[d];
    const created = new Date(NOW.getTime() - hoursAgo * 3600 * 1000);
    const expires = new Date(created.getTime() + 24 * 3600 * 1000);
    const dur = `0:${String(((d * 7) % 50) + 8).padStart(2, "0")}`;
    const hh = String(created.getHours()).padStart(2, "0");
    const mm = String(created.getMinutes()).padStart(2, "0");
    clips.push({
      id: `vc-${String(d + 1).padStart(3, "0")}`,
      title: `Clipe das ${hh}:${mm}`,
      thumb: thumbs[d % thumbs.length],
      duration: dur,
      createdAt: created.toISOString(),
      expiresAt: expires.toISOString(),
      date: created.toISOString().slice(0, 10),
      time: `${hh}:${mm}`,
    });
  }
  return clips;
})();

window.NOW_REF = new Date(2026, 4, 8, 23, 30, 0);

window.formatDate = function (iso) {
  const d = new Date(iso + "T12:00:00");
  const dd = String(d.getDate()).padStart(2, "0");
  const months = ["jan","fev","mar","abr","mai","jun","jul","ago","set","out","nov","dez"];
  return `${dd} ${months[d.getMonth()]}`;
};

window.relativeTime = function (createdIso) {
  const created = new Date(createdIso);
  const diffMin = Math.floor((window.NOW_REF - created) / 60000);
  if (diffMin < 1) return "agora";
  if (diffMin < 60) return `${diffMin} min atrás`;
  const h = Math.floor(diffMin / 60);
  if (h < 24) return `${h}h atrás`;
  const d = Math.floor(h / 24);
  return `${d}d atrás`;
};

window.timeRemaining = function (expiresIso) {
  const expires = new Date(expiresIso);
  const diffMin = Math.max(0, Math.floor((expires - window.NOW_REF) / 60000));
  const urgency = 1 - Math.min(1, diffMin / (24 * 60));
  let text;
  if (diffMin <= 0) text = "expirado";
  else if (diffMin < 60) text = `expira em ${diffMin}min`;
  else {
    const h = Math.floor(diffMin / 60);
    const m = diffMin % 60;
    text = m > 0 && h < 6 ? `expira em ${h}h${String(m).padStart(2, "0")}` : `expira em ${h}h`;
  }
  return { text, urgency, minutes: diffMin };
};
