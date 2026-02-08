const mysql = require('mysql2');

const db = mysql.createConnection({
  host: 'ztechdb.cyb0o8u2oqp9.us-east-1.rds.amazonaws.com',
  user: 'ghufranataie',
  password: 'DefaultGTRPassDBac1',
  database: 'rocktime'
});

db.connect((err) => {
  if (err) {
    console.error('DB connection failed:', err.message);
  } else {
    console.log('Connected to MySQL');
  }
});

module.exports = db;