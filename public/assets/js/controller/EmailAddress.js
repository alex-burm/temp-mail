(function() {
    class EmailAddressController extends Stimulus.Controller {

        static targets = ['email', 'copy'];

        connect() {
            this.listSection = document.querySelector('.inbox-section');
            this.viewSection = document.querySelector('.email-view-section');

            this.resolveLiveController();
            this.live.on('render:finished', this.onRenderFinished.bind(this));
            this.resolveAddress();
            this.handleCopy();
        }

        onRenderFinished() {
            if (false === this.hasEmailTarget) {
                return;
            }
            const email = this.emailTarget.value;
            if (!email) {
                return;
            }
            localStorage.setItem('tmp-mail:address', email);

            const controller = this.application.getControllerForElementAndIdentifier(this.listSection, 'MessageList');
            controller.list();
        }

        refresh() {
            this.live.action('refresh')
        }

        resolveLiveController() {
            const controller = this.application.getControllerForElementAndIdentifier(this.element, 'live');

            if (!controller) {
                throw new Error('live controller not found');
            }

            this.live = controller.component;
        }

        resolveAddress() {
            const value = localStorage.getItem('tmp-mail:address');
            if (value) {
                this.emailTarget.value = value;
            } else {
                this.refresh();
            }
        }

        async handleCopy() {
            this.copyTarget.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(this.emailTarget.value);
                } catch (err) {
                    this.emailTarget.select();
                    document.execCommand('copy');
                }

                showToast('✅ The email address has been copied!');
            });
        }
    }

    const timer = setInterval(() => {
        if (window.Stimulus) {
            Stimulus.register('EmailAddress', EmailAddressController);
            clearInterval(timer);
        }
    }, 10);
})();

