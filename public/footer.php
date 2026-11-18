<footer class="pc-footer no-print">
    <div class="footer-wrapper container-fluid">
        <div class="row">
            <div class="col my-1">
                <p class="m-0">© OGPurchasing &#9829; Developed by <a href="https://www.toyotanorthedsa.com/">Toyota North EDSA</a></p>
            </div>
            <div class="col-auto my-1">
                <ul class="list-inline footer-link mb-0">
                    <li class="list-inline-item"><a href="dashboard">Home</a></li>
                </ul>
            </div>
        </div>
    </div>
</footer>

<!-- [Page Specific JS] start -->
<script src="assets/js/plugins/apexcharts.min.js"></script>
<script src="assets/js/pages/dashboard-default.js"></script>
<!-- [Page Specific JS] end -->
<!-- Required Js -->
<script src="assets/js/plugins/popper.min.js"></script>
<script src="assets/js/plugins/simplebar.min.js"></script>
<script src="assets/js/plugins/bootstrap.min.js"></script>
<script src="assets/js/fonts/custom-font.js"></script>
<script src="assets/js/script.js"></script>
<script src="assets/js/theme.js"></script>
<script src="assets/js/plugins/feather.min.js"></script>

<script>
    change_box_container('false');
</script>

<script>
    layout_caption_change('true');
</script>

<script>
    layout_rtl_change('false');
</script>

<script>
    preset_change("preset-1");
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/nprogress/0.2.0/nprogress.min.js"></script>
<script>
    // Tribute.js Global Init
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Tribute !== 'undefined') {
            const tribute = new Tribute({
                values: function(text, cb) {
                    $.ajax({
                        url: 'data/search_users.php',
                        data: { q: text },
                        dataType: 'json',
                        success: (data) => {
                            const formatted = data.map(u => ({
                                key: u.full_name + " (" + u.username + ")",
                                value: u.full_name
                            }));
                            cb(formatted);
                        }
                    });
                },
                selectTemplate: function(item) {
                    return '@' + item.original.value;
                },
                requireLeadingSpace: true,
                noMatchTemplate: '<li style="padding:10px; color:#999;">No users found</li>'
            });

            // Attach to all current comment inputs and textareas
            const attachTribute = () => {
                tribute.attach(document.querySelectorAll('input[name="comments"], textarea[name="comments"], #editCommentText, .mention-input'));
            };

            attachTribute();

            // Watch for dynamically added inputs (like reply forms)
            $(document).on('click', '.reply-btn', function() {
                setTimeout(attachTribute, 200);
            });

            // Watch for Bootstrap modals
            $(document).on('shown.bs.modal', function() {
                attachTribute();
            });

            // Global JS Highlight Mention function
            window.highlightMentionsJS = function(text) {
                if (!text) return text;
                // Match @ followed by words (name)
                // Since names can have spaces, we'll try a greedy approach or match against known names if passed.
                // For simplicity, we'll match @[Name] or just @Name using a regex for names.
                // A common pattern for Tribute is that names are inserted as @Full Name.
                // We'll use a regex that looks for @ followed by characters until a break.
                // However, we want to be careful with spaces. 
                // Let's use a simpler heuristic: match @... and wrap if it looks like a name.
                // Better: The backend already handles the logic, but for Pusher real-time, we need this.
                return text.replace(/@([A-Za-z0-9 ]+?)(?=\s|$|[,.?!])/g, '<span class="mention-highlight">@$1</span>');
            };
        }
    });

    NProgress.start();
    window.addEventListener('load', function () {
        NProgress.done();
    });
</script>
</body>
<!-- [Body] end -->

</html>