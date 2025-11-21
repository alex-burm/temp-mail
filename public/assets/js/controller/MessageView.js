(function() {
    class MessageViewController extends Stimulus.Controller {

        connect() {
            this.resolveLiveController();
            this.tabs();

            this.listSection = document.querySelector('.inbox-section');
            this.viewSection = document.querySelector('.email-view-section');
        }

        back() {
            const controller = this.application.getControllerForElementAndIdentifier(this.listSection, 'MessageList');
            controller.list();
        }

        tabs() {
            const buttons = document.querySelectorAll('.email-view-tabs .tab-btn');
            const tabs = document.querySelectorAll('.tab-content');

            const reset = () => {
                buttons.forEach(b => b.classList.remove('active'))
                tabs.forEach(b => b.classList.remove('active'))
            }
            buttons.forEach(btn => {
                btn.addEventListener('click', e => {
                    reset();

                    btn
                        .classList
                        .add('active')

                    document.querySelector(`.tab-content[data-tab="${btn.dataset.tab}"]`)
                        .classList
                        .add('active')
                })
            })

        }

        resolveLiveController() {
            const controller = this.application.getControllerForElementAndIdentifier(this.element, 'live');

            if (!controller) {
                throw new Error('live controller not found');
            }

            this.live = controller.component;
        }
    }

    const timer = setInterval(() => {
        if (window.Stimulus) {
            Stimulus.register('MessageView', MessageViewController);
            clearInterval(timer);
        }
    }, 10);
})();

