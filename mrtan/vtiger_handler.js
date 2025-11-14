// Module to handle all vTiger database operations (Deduplication, Create, Log)
const mysql = require('mysql2/promise');
const crypto = require('crypto'); // Used for generating unique vTiger IDs

// ⚠️ IMPORTANT: Please replace these placeholders with your actual vTiger DB credentials
const dbConfig = {
    host: 'localhost', // <--- IP Address หรือ Domain ของ Database Server
    user: 'vtiger_idea',     // <--- Username ของ MySQL (ไม่ใช่ vTiger Login User)
    password: 'e0fc86d8d85868', // <--- Password ของ MySQL User นั้น
    database: 'vtiger_idea', // <--- ชื่อ Database ของ vTiger (โดยปกติคือ vtiger)
    // vTiger often uses custom table prefixes, adjust if necessary
};

// --- Helper Functions for vTiger IDs and Relational Tables ---

/**
 * Generates a unique vTiger-style ID (e.g., 1x38475)
[Immersive content redacted for brevity.]