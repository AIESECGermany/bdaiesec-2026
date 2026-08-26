const mysql = require('mysql2/promise');
const fs = require('fs');
const path = require('path');
require('dotenv').config({ path: path.join(__dirname, '..', '.env') });

async function main() {
  const config = {
    host: process.env.DB_HOST || 'localhost',
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    charset: 'utf8mb4'
  };

  const connection = await mysql.createConnection(config);
  const databaseName = process.env.DB_NAME || 'aiesec_leads';

  await connection.query(`CREATE DATABASE IF NOT EXISTS \`${databaseName}\`;`);
  await connection.query(`USE \`${databaseName}\`;`);

  const schema = fs.readFileSync(path.join(__dirname, '..', 'db', 'schema.sql'), 'utf8');
  await connection.query(schema);

  console.log(`Database '${databaseName}' initialized successfully.`);
  await connection.end();
}

main().catch((error) => {
  console.error('Failed to initialize MariaDB schema:', error.message);
  process.exit(1);
});
