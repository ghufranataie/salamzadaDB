const express = require('express');
const bodyParser = require('body-parser');
const cors = require('cors');
const multer = require('multer');
const path = require('path');
const db = require('./db');

const app = express();
const PORT = 3000;

// Middleware
app.use(cors());
app.use(bodyParser.json());
app.use('/uploads', express.static(path.join(__dirname, 'uploads')));

// Multer config for file uploads
const storage = multer.diskStorage({
  destination: (req, file, cb) => {
    cb(null, 'uploads/');
  },
  filename: (req, file, cb) => {
    cb(null, Date.now() + path.extname(file.originalname));
  }
});
const upload = multer({ storage });

// -------------------- ROUTES -------------------- //

// GET all individuals
app.get('/individuals', (req, res) => {
  const sql = "SELECT * FROM individuals";
  db.query(sql, (err, results) => {
    if (err) return res.status(500).json(err);
    res.json(results);
  });
});

// GET individual by ID
app.get('/individuals/:id', (req, res) => {
  const sql = "SELECT * FROM individuals WHERE indID = ?";
  db.query(sql, [req.params.id], (err, results) => {
    if (err) return res.status(500).json(err);
    res.json(results[0]);
  });
});

// CREATE a new individual
app.post('/individuals', upload.single('indPhoto'), (req, res) => {
  const { indName, indLastName, indPhone, indEmail } = req.body;
  const indPhoto = req.file ? req.file.filename : null;
  const sql = "INSERT INTO individuals (indName, indLastName, indPhone, indEmail, indPhoto) VALUES (?, ?, ?, ?, ?)";
  db.query(sql, [indName, indLastName, indPhone, indEmail, indPhoto], (err, result) => {
    if (err) return res.status(500).json(err);
    res.json({ message: 'Individual added', id: result.insertId });
  });
});

// UPDATE individual
app.put('/individuals/:id', upload.single('indPhoto'), (req, res) => {
  const { indName, indLastName, indPhone, indEmail } = req.body;
  const indPhoto = req.file ? req.file.filename : null;

  let sql, params;
  if (indPhoto) {
    sql = "UPDATE individuals SET indName=?, indLastName=?, indPhone=?, indEmail=?, indPhoto=? WHERE indID=?";
    params = [indName, indLastName, indPhone, indEmail, indPhoto, req.params.id];
  } else {
    sql = "UPDATE individuals SET indName=?, indLastName=?, indPhone=?, indEmail=? WHERE indID=?";
    params = [indName, indLastName, indPhone, indEmail, req.params.id];
  }

  db.query(sql, params, (err, result) => {
    if (err) return res.status(500).json(err);
    res.json({ message: 'Individual updated' });
  });
});

// DELETE individual
app.delete('/individuals/:id', (req, res) => {
  const sql = "DELETE FROM individuals WHERE indID=?";
  db.query(sql, [req.params.id], (err, result) => {
    if (err) return res.status(500).json(err);
    res.json({ message: 'Individual deleted' });
  });
});

// Start server
app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});
