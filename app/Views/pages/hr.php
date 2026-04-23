<section class="panel">
    <h2>HR Management Console</h2>

    <form id="hr-login-form" class="inline-form">
        <input type="password" name="password" placeholder="HR Console Password" required>
        <button type="submit">Login</button>
        <button id="hr-logout" type="button">Logout</button>
    </form>

    <div class="grid-2">
        <div>
            <form id="hr-filter-form" class="inline-form">
                <input name="reference_no" placeholder="Reference">
                <input name="category" placeholder="Category">
                <select name="status">
                    <option value="">Any status</option>
                    <option>Investigation pending</option>
                    <option>Investigation in progress</option>
                    <option>Investigation completed</option>
                </select>
                <button type="submit">Load Cases</button>
            </form>
            <div id="hr-cases-table"></div>
        </div>

        <div>
            <form id="hr-update-form">
                <label>Reference</label>
                <input name="reference_no" required>
                <label>Priority</label>
                <select name="priority">
                    <option>Low</option>
                    <option selected>Normal</option>
                    <option>High</option>
                    <option>Critical</option>
                </select>
                <label>Stage</label>
                <input name="stage" value="Logged">
                <label>Status</label>
                <select name="status">
                    <option>Investigation pending</option>
                    <option>Investigation in progress</option>
                    <option>Investigation completed</option>
                </select>
                <label>Anonymized Summary</label>
                <textarea name="anonymized_summary"></textarea>
                <label>Action Taken</label>
                <textarea name="action_taken"></textarea>
                <label>Outcome Comments</label>
                <textarea name="outcome_comments"></textarea>
                <label>Internal Notes</label>
                <textarea name="internal_notes"></textarea>
                <label><input type="checkbox" name="acknowledge" value="1"> Acknowledge Case</label>
                <button type="submit">Update Case</button>
            </form>
            <pre id="hr-output" class="output"></pre>
        </div>
    </div>
</section>
