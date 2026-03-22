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
