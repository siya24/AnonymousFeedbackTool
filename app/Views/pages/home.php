<section class="panel">
    <h2>Anonymous Feedback Submission</h2>
    <p class="support">Support and protection: retaliation and victimization are prohibited. This platform is confidential and intended for safe reporting.</p>

    <div class="tabs">
        <button data-tab="new" class="active">New Feedback</button>
        <button data-tab="followup">Follow-up</button>
        <button data-tab="reporting">Employee Reporting</button>
    </div>

    <div id="tab-new" class="tab-content active">
        <form id="new-feedback-form" enctype="multipart/form-data">
            <label>Category</label>
            <select name="category" required>
                <option value="">Select category</option>
                <option>Discrimination</option>
                <option>Harassment or Bullying</option>
                <option>Unfair Workload Distribution</option>
                <option>Managerial Misconduct</option>
                <option>Psychological Safety Concerns</option>
                <option>Other</option>
            </select>
            <label>Description (max 5000)</label>
            <textarea name="description" maxlength="5000" required></textarea>
            <label>Attachments (optional)</label>
            <input type="file" name="attachments[]" multiple>
            <button type="submit">Submit Anonymously</button>
        </form>
    </div>

    <div id="tab-followup" class="tab-content">
        <form id="followup-form" enctype="multipart/form-data">
            <label>Reference Number</label>
            <input name="reference_no" required>
            <label>Update Details (max 5000)</label>
            <textarea name="update_text" maxlength="5000" required></textarea>
            <label>Attachments (optional)</label>
            <input type="file" name="attachments[]" multiple>
            <button type="submit">Submit Follow-up</button>
        </form>
        <form id="lookup-form">
            <label>Lookup Existing Reference</label>
            <input name="reference_no" required>
            <button type="submit">Retrieve</button>
        </form>
        <pre id="lookup-output" class="output"></pre>
    </div>

    <div id="tab-reporting" class="tab-content">
        <form id="public-report-filter">
            <input name="reference_no" placeholder="Reference number">
            <input name="category" placeholder="Category">
            <select name="status">
                <option value="">Any status</option>
                <option>Investigation pending</option>
                <option>Investigation in progress</option>
                <option>Investigation completed</option>
            </select>
            <input type="date" name="date">
            <button type="submit">Search</button>
        </form>
        <div id="public-report-table"></div>
    </div>

    <pre id="global-output" class="output"></pre>
</section>
