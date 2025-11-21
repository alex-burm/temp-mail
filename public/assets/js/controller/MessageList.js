(function() {
    class MessageListController extends Stimulus.Controller {

        connect() {
            this.resolveLiveController();
            this.autoRefresh();

            this.listSection = document.querySelector('.inbox-section');
            this.viewSection = document.querySelector('.email-view-section');
        }

        refresh() {
            const email = localStorage.getItem('tmp-mail:address')
            if (!email) {
                return;
            }

            this.live.action('refresh', { email })
        }

        autoRefresh() {
            if (this.interval) {
                return;
            }
            this.interval = setInterval(() => this.refresh(), 10000)
        }

        open(event) {
            clearInterval(this.interval);
            const id = event.currentTarget.dataset.id;

            this.listSection.style.display = 'none';
            this.viewSection.style.display = 'block';

            const controller = this.application.getControllerForElementAndIdentifier(this.viewSection, 'MessageView');
            controller.live.action('load', { id });

        }

        list() {
            this.listSection.style.display = 'block';
            this.viewSection.style.display = 'none';

            this.refresh();
            this.autoRefresh();
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
            Stimulus.register('MessageList', MessageListController);
            clearInterval(timer);
        }
    }, 10);
})();

