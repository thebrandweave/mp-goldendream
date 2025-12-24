<!-- loader.php -->
<div class="loader-overlay" id="globalLoader">
    <svg class="loader" viewBox="0 0 100 100">
        <circle class="circle" cx="50" cy="50" r="10"></circle>
        <circle class="circle" cx="50" cy="50" r="20"></circle>
        <circle class="circle" cx="50" cy="50" r="30"></circle>
        <circle class="circle" cx="50" cy="50" r="40"></circle>
    </svg>
</div>

<style>
.loader-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    z-index: 999999;
    transition: opacity 0.3s ease;
}
.loader-overlay.hide {
    opacity: 0;
    pointer-events: none;
}
.loader {
    width: 300px;
    height: 100px;
    overflow: visible;
}
.circle {
    fill: none;
    stroke-width: 4;
    stroke-linecap: round;
    stroke-dasharray: 0, 314;
    animation: draw 2s ease-in-out infinite;
    filter: drop-shadow(0 0 15px currentColor);
    transform-origin: center;
}
.circle:nth-child(1) { stroke: #3a7bd5; animation-delay: 0s; }
.circle:nth-child(2) { stroke: #00d2ff; animation-delay: 0.4s; }
.circle:nth-child(3) { stroke:rgb(27, 83, 161); animation-delay: 0.8s; }
.circle:nth-child(4) { stroke: #00d2ff; animation-delay: 1.2s; }

@keyframes draw {
    0%   { stroke-dasharray: 0, 314; opacity: 0.2; transform: scale(0.8); }
    50%  { opacity: 1; }
    100% { stroke-dasharray: 314, 314; opacity: 0.2; transform: scale(1.2); }
}
</style>

<script>
    // Hide loader after page fully loads
    window.addEventListener('load', () => {
        document.getElementById('globalLoader').classList.add('hide');
    });

    // Show loader immediately before navigating to another page
    document.querySelectorAll('a[href]:not([target])').forEach(link => {
        link.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href && !href.startsWith('#') && !href.startsWith('javascript:')) {
                document.getElementById('globalLoader').classList.remove('hide');
            }
        });
    });

    // Show loader immediately before form submission
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => {
            document.getElementById('globalLoader').classList.remove('hide');
        });
    });

    // Optional: also trigger loader for manual navigation
    window.addEventListener('beforeunload', () => {
        document.getElementById('globalLoader').classList.remove('hide');
    });
</script>
