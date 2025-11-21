const showToast = msg => {
    const toast = document.createElement('div')
    toast.classList.add('toast');
    toast.textContent = msg;
    toast.classList.add('show');

    document.body.append(toast);

    setTimeout(() => toast.remove(), 3000);
}

const updateSizeIframe = (obj, offset = 0) => {
    obj.style.height = obj.contentWindow.document.documentElement.scrollHeight + offset + 'px';
}

document.querySelector('.nav-toggle').addEventListener('click', () => {
    document.querySelector('.nav-menu').classList.toggle('active');
});
