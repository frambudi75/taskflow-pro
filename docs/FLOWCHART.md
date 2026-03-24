# 🔄 TaskFlow Pro Logic Flowcharts

These diagrams illustrate the core logic of the application.

## 🔑 Authentication & Access Flow
```mermaid
graph TD
    A[User Opens Site] --> B{Logged In?}
    B -- No --> C[Login Page]
    C --> D[Enter Credentials]
    D --> E{Valid?}
    E -- Yes --> F[Dashboard]
    E -- No --> C
    B -- Yes --> F
```

## 📋 Kanban & Task Status Flow
```mermaid
graph LR
    T[Todo] -->|Start| IP[In Progress]
    IP -->|Complete| D[Done]
    D -->|Reopen| T
    IP -->|Pause| T
```

## 📈 Activity Logging System
```mermaid
graph TD
    U[User Action] --> P[PHP Logic Executed]
    P --> DB[Record to Module Table]
    P --> LA[logActivity Function]
    LA --> ACT[Insert to activities table]
    ACT --> DIS[Display in Activity Center]
```

## 💬 Real-Time Chat & Sound Notification Flow
```mermaid
graph TD
    A[User 1 Sends Message] --> B[API api_chat.php]
    B --> C[(MySQL messages table)]
    D[User 2 Browser Polling] --> E{New Message?}
    E -- Yes --> F[Update Badge & List]
    F --> G[Play 'Ding' Sound]
    F --> H[Browser Push Notification]
    E -- No --> D
```

## 📧 Multi-Channel Daily Notification Flow
```mermaid
graph TD
    CR[aaPanel Cron Job] -->|Every Hour| NH[notif_harian.php]
    NH --> SET[Check settings.php config]
    SET --> TASK[Query Due Tasks]
    TASK --> USER[Get User SMTP/Telegram Info]
    USER --> SND{Send via?}
    SND --> MAIL[PHPMailer SMTP]
    SND --> TELE[Telegram Bot API]
    MAIL --> OK[Notification Received]
    TELE --> OK
```

## 📂 Document Management & Preview Flow
```mermaid
graph TD
    U[Upload Task Doc] --> P[PHP serve_file.php]
    P --> STOR[Storage/uploads/documents]
    V[User View Document] --> SEC{Secure Check?}
    SEC -- Yes --> PREV[Dynamic Preview]
    SEC -- No --> R[Redirect Login]
```
