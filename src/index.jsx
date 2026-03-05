import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';
const el = document.getElementById('linkrise-admin-root');
if (el) createRoot(el).render(<App />);
