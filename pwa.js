// PWA Installation and Service Worker Registration
class PWAHelper {
    constructor() {
        this.deferredPrompt = null;
        this.init();
    }

    init() {
        this.registerServiceWorker();
        this.setupInstallPrompt();
        this.detectStandaloneMode();
    }

    // Register Service Worker
    registerServiceWorker() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => {
                        console.log('SW registered: ', registration);
                        this.updateAppStatus('Service Worker registered successfully');
                    })
                    .catch(registrationError => {
                        console.log('SW registration failed: ', registrationError);
                        this.updateAppStatus('Service Worker registration failed');
                    });
            });
        }
    }

    // Handle install prompt
    setupInstallPrompt() {
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            this.deferredPrompt = e;
            this.showInstallButton();
        });

        window.addEventListener('appinstalled', (evt) => {
            console.log('PWA was installed successfully');
            this.hideInstallButton();
            this.updateAppStatus('App installed successfully!');
        });
    }

    // Show install button
    showInstallButton() {
        let installButton = document.getElementById('installButton');
        if (!installButton) {
            installButton = this.createInstallButton();
        }
        installButton.style.display = 'block';
    }

    hideInstallButton() {
        const installButton = document.getElementById('installButton');
        if (installButton) {
            installButton.style.display = 'none';
        }
    }

    // Create install button dynamically
    createInstallButton() {
        const button = document.createElement('button');
        button.id = 'installButton';
        button.className = 'pwa-install-btn';
        button.innerHTML = '<i class="fas fa-download"></i> Install App';
        button.addEventListener('click', () => this.installApp());
        
        // Add to header if available, otherwise to body
        const header = document.querySelector('.header-logout') || document.body;
        if (header.classList.contains('header-logout')) {
            header.parentNode.insertBefore(button, header.nextSibling);
        } else {
            document.body.appendChild(button);
        }
        
        return button;
    }

    // Install app
    installApp() {
        if (this.deferredPrompt) {
            this.deferredPrompt.prompt();
            this.deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted the install prompt');
                    this.updateAppStatus('App installation in progress...');
                } else {
                    console.log('User dismissed the install prompt');
                }
                this.deferredPrompt = null;
            });
        }
    }

    // Detect if app is running in standalone mode
    detectStandaloneMode() {
        if (this.isRunningStandalone()) {
            document.documentElement.classList.add('pwa-standalone');
            this.updateAppStatus('Running in app mode');
        }
    }

    isRunningStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches || 
               window.navigator.standalone ||
               document.referrer.includes('android-app://');
    }

    updateAppStatus(message) {
        console.log('PWA Status:', message);
        // You can show this in a toast notification if needed
    }
}

// Initialize PWA when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new PWAHelper();
});

// Online/Offline detection
window.addEventListener('online', () => {
    document.documentElement.classList.remove('offline');
    console.log('App is online');
});

window.addEventListener('offline', () => {
    document.documentElement.classList.add('offline');
    console.log('App is offline');
    // You could show an offline notification here
});