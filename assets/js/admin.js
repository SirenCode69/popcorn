const popcornColorPicker = document.getElementById('popcorn-accent');
const popcornColorHex = document.getElementById('popcorn-accent-hex');
const popcornAdmin = document.querySelector('.pc-admin');

if (popcornColorPicker && popcornColorHex && popcornAdmin) {
  popcornColorPicker.addEventListener('input', () => {
    popcornColorHex.value = popcornColorPicker.value.toUpperCase();
    popcornAdmin.style.setProperty('--pc-admin-accent', popcornColorHex.value);
  });

  popcornColorHex.addEventListener('input', () => {
    if (/^#[0-9a-fA-F]{6}$/.test(popcornColorHex.value)) {
      popcornColorPicker.value = popcornColorHex.value;
      popcornAdmin.style.setProperty('--pc-admin-accent', popcornColorHex.value);
    }
  });
}
