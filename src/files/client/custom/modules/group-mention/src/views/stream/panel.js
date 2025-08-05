define("group-mention:views/stream/panel", ["views/stream/panel", "group-mention:views/note/fields/post"], function (
    Dep, _post
) {
    _post = _interopRequireDefault(_post);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : { default: e }; }
    return Dep.extend({
        setup: function () {
            console.log("here");
            Dep.prototype.setup.call(this);
            this.postFieldView = new _post.default({
                name: 'post',
                mode: 'edit',
                params: {
                    required: true,
                    rowsMin: 1,
                    preview: false,
                    attachmentField: 'attachments'
                },
                model: this.seed,
                placeholderText: this.placeholderText,
                noResize: true,
                recordHelper: this.formRecordHelper
            });
            this.assignView('postField', this.postFieldView, '.textarea-container').then(view => {
                this.initPostEvents(view);
            });
            this.wait(this.createCollection().then(() => this.setupPinned()));
            this.listenTo(this.seed, 'change:attachmentsIds', () => {
                this.controlPostButtonAvailability();
            });
        },
    });
});
