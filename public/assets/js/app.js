window.addEventListener('DOMContentLoaded', () => {
    const application = Stimulus.Application.start();
    application.register('live', LiveController);
    application.debug = true;

    window.Stimulus = application
})
