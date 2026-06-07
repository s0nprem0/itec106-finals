# ITEC106 Finals - Hardware Price Prediction Game

A web-based investment simulation game where players predict hardware component price movements to grow their virtual capital.

**Project Title:** Hardware Market Trader  
**Course:** ITEC106 - [Course Name]  
**Student:** [Your Name]  
**Date:** June 2026

---

## 🎮 Game Overview

**"Hardware Trader"** is an engaging price prediction game where players act as virtual investors in the PC hardware market. The objective is to grow your starting capital to **$50,000** within a maximum of 20 rounds by correctly predicting whether the price of the next hardware item will go **Higher** or **Lower**.

### Key Features

- **Three Difficulty Levels** with balanced volatility and payouts
- **Pre-generated Asset Sequence** for fair and reproducible gameplay
- **Realistic Hardware Items** (GPUs, CPUs, Consoles, Peripherals, etc.)
- **Risk Management** — Maximum 25% bet per round
- **Session-Based State Management**
- **Leaderboard System**
- **Responsive Cyber-Tech UI**

---

## 🎯 How to Play

1. **Choose Difficulty**
   - **Easy** (±18% volatility) — $18,000 starting | 1.45x payout
   - **Medium** (±28% volatility) — $12,000 starting | 1.75x payout
   - **Hard** (±42% volatility) — $6,500 starting | 2.4x payout

2. **Make Predictions**
   - Bet up to **25%** of your current balance
   - Predict if the next item's price will be **Higher** or **Lower**

3. **Win Condition**
   - Reach **$50,000** before going broke or completing 20 rounds

---

## 📊 Volatility Explained

**Volatility** determines how much prices can swing each round:

- **Higher volatility** = Larger price movements = Higher risk & reward
- Prices are randomly adjusted within the volatility range from the item's base price
- All 20 rounds are pre-generated at the start of each game for fairness

---

## 🛠️ Technology Stack

- **Backend**: PHP 8+ with PDO
- **Database**: MySQL / MariaDB
- **Frontend**: HTML5, CSS3, JavaScript
- **Architecture**: MVC-style (Core, Public, Views)
- **State Management**: PHP Sessions

---

## 📁 Project Structure

```
itec106-finals/
├── core/                  # Core logic
│   ├── game_engine.php
│   ├── database.php
│   └── auth.php
├── public/                # Public pages
│   ├── game.php
│   ├── index.php
│   └── leaderboard.php
├── views/
│   ├── game/
│   │   └── board.php
│   └── partials/
├── assets.sql             # Database schema and sample data
└── README.md
```


---

## 🚀 Installation & Setup

1. Clone the repository
2. Import the database schema (`assets.sql`)
3. Configure database credentials in `core/database.php`
4. Place the project in your web server directory
5. Ensure `sessions` and `uploads` directories have write permissions (if needed)

---

## 🎨 UI/UX Highlights

- Clean cyber-tech aesthetic
- Keyboard shortcuts support (`E`/`M`/`H` for difficulties, `Enter`/`Space` to continue)
- Real-time balance and progress tracking
- Responsive design

---

## 🔧 Recent Improvements

- Pre-generated asset sequences for fairness
- Improved difficulty balancing
- Enhanced state management and restart functionality
- Better game instructions with volatility explanation
- Fixed result screen and start screen layout

---

## 📈 Future Enhancements

- CSRF protection
- Real streak tracking
- Advanced leaderboard filters
- Sound effects and animations
- Game history / replay system

---

## 📝 License

This project is submitted as a final requirement for **ITEC106**. All rights reserved.

---