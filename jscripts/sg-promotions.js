
// Use window.dynamicAds if available, otherwise fallback to backup ads
const backupAds = [
  { img: 'https://hostpro.top/images/ad-hostpro.jpg', url: 'https://hostpro.top/' },
  { img: '/img/ad2.png', url: 'https://referral2.com' },
  { img: '/img/ad3.png', url: 'https://referral3.com' }
];

function getAds() {
  return window.dynamicAds && Array.isArray(window.dynamicAds) && window.dynamicAds.length > 0
    ? window.dynamicAds
    : backupAds;
}

function showRandomAd() {
  const ads = getAds();
  const ad = ads[Math.floor(Math.random() * ads.length)];
  const sidebar = document.getElementById('promo-sidebar');
  if (sidebar) {
    sidebar.innerHTML =
      `<a href="${ad.url}" target="_blank">
        <img src="${ad.img}" style="width:100%;display:block;">
      </a>`;
  }
}

document.addEventListener('DOMContentLoaded', function() {
  showRandomAd();
});