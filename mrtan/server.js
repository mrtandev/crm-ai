// Express Server for Line OA Deduplication and vTiger Integration
const express = require('express');
const bodyParser = require('body-parser');
const vtigerHandler = require('./vtiger_handler');

const app = express();
const PORT = 3000;

// Middleware to parse JSON bodies
app.use(bodyParser.json());

/**
 * @route POST /api/process-lead
 * @description Main endpoint called by n8n to process Lead Generation Intent.
 * It handles deduplication, lead creation, and chat logging.
 * * @param {string} line_user_id - Line user ID from Line OA Webhook
 * @param {string} message - The initial message sent by the user
 */
app.post('/api/process-lead', async (req, res) => {
    const { line_user_id, message } = req.body;

    // Validate input data
    if (!line_user_id || !message) {
        return res.status(400).json({ status: 'error', message: 'Missing line_user_id or message in request body.' });
    }

    try {
        // Call the business logic handler
        const result = await vtigerHandler.handleLeadProcess(line_user_id, message);

        // Send the result back to n8n
        // Result will contain: { status: 'success', action: 'CREATED_LEAD' | 'LOGGED_CHAT', id: '...' }
        return res.status(200).json(result);

    } catch (error) {
        console.error('API Error during lead processing:', error);
        // Respond with a generic server error
        return res.status(500).json({ status: 'error', message: 'Internal Server Error during vTiger operation.' });
    }
});

app.listen(PORT, () => {
    console.log(`Server running on http://localhost:${PORT}`);
    console.log('API Endpoint: POST http://localhost:3000/api/process-lead');
});

// Note: In a production environment, you should use HTTPS and environment variables 
// for sensitive data like database credentials.