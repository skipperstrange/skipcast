# SkipCast Development Roadmap

## Phase 1: Core Functionality Development

### 1.1 User Registration and Authentication
- Implement secure login and registration.
- Role-based access control for normal users and DJs.

### 1.2 Audio Channel Creation
- Allow users to create up to **5 free channels**, enforced through database constraints and application logic checks.
- Generate playlists automatically from uploaded audio files.

### 1.3 Channel Management
- Activate/Deactivate channels (On Air/Off Air toggle).
- Manage playlists: reorder, remove, and add new audio tracks.
- Provide basic channel analytics (listener count, total plays).

### 1.4 Streaming Integration
- Integrate **Liquidsoap** or **Icecast/Ezstream** for seamless playlist streaming.
- Set up shell commands or API connections to manage streaming services.

### 1.5 User Interaction with Channels
- Enable users to discover and follow channels.
- Stream audio from active channels.
- Save favorite channels for quick access.

### 1.6 Monetization
- Introduce a subscription model for creating additional channels beyond the free tier.

---

## Phase 2: Advanced Features and Community Tools

### 2.1 Community Features
- Enable social interactions like comments, likes, and shares.
- Build community-driven playlists (e.g., trending tracks or top channels).

### 2.2 Premium Streaming and Analytics
- Advanced streaming options for premium users.
- Detailed analytics dashboards: listener demographics, engagement metrics.

---

## Phase 3: Live Streaming, Event Booking, and Ticketing

### 3.1 Live Streaming to Channels
- **Features**:
  - Allow users to stream live audio directly to their channels.
  - Display real-time listener analytics (number of listeners, location).
  - Notify followers when a channel goes live.

### 3.2 Event Booking System
- **Features**:
  - Enable users to book events and add them to personal calendars.
  - Event hosts can set details like title, description, location, and time.
  - Attach audio previews or promotional content to events.

### 3.3 Notifications and Reminders
- **Features**:
  - Send calendar reminders for booked events.
  - Trigger proximity-based notifications for users near event locations.
  - Provide alerts for event start times and updates.

### 3.4 Ticketing System
- **Features**:
  - Event hosts set ticket prices (free, fixed, or donation-based).
  - Users can purchase or RSVP for tickets.
  - Payment processing integrated with **Stripe** or **PayPal** for secure transactions.
  - Generate QR code tickets for seamless verification.
  - Provide analytics for ticket sales and attendee engagement.

---

## Key Workflows

### **Channel Creation Workflow**
1. User logs in and navigates to "Create Channel".
2. Fills in details: name, description, category.
3. Uploads audio files.
4. System generates a playlist and activates the channel.

### **Streaming Integration Workflow**
1. User toggles "On Air" to activate the channel.
2. Backend triggers Liquidsoap/Icecast to start streaming.
3. Stream becomes available to listeners.
4. Listener analytics are updated in real-time.

### **Live Streaming Workflow** (Phase 3)
1. User starts live streaming from the dashboard.
2. System connects to Liquidsoap/Icecast for real-time transmission.
3. Followers receive notifications about the live stream.
4. Real-time analytics display listener engagement.

### **Event Booking Workflow** (Phase 3)
1. Event host creates an event, adding details like title, description, and location.
2. Users browse events and book tickets.
3. Booked events are added to users' calendars with reminders.
4. QR codes are generated for ticket verification at the event.

### **Ticketing Workflow** (Phase 3)
1. Event host sets ticket price and availability.
2. Users purchase tickets through the platform.
3. Payment is processed securely via **Stripe** or **PayPal**, and tickets are issued with unique QR codes.
4. Hosts track ticket sales and attendee engagement.

---

## Tech Stack

### Backend: PHP (Laravel)
- **Authentication**: Laravel Passport/Sanctum.
- **Task Scheduling**: Laravel Scheduler for periodic jobs.
- **Queue Management**: Laravel Horizon with Redis.
- **Storage**: AWS S3 or local storage for audio files.

### Frontend: Vue/Nuxt
- **Framework**: Nuxt.js for SSR and SPA capabilities.
- **State Management**: Vuex for global state.
- **UI Library**: Tailwind CSS.

### Real-Time Features
- **WebSockets**: Laravel Echo with Pusher for real-time updates.
- **Notifications**: Push notifications using Vue Push Notifications.

### Streaming Tools
- **Audio Streaming**: Liquidsoap or Icecast/Ezstream.
- **Player**: Howler.js or Wavesurfer.js for audio playback.

---

## Deployment

### Backend
- **Hosting**: AWS, DigitalOcean, or Vultr.
- **Web Server**: Nginx or Apache.
- **CI/CD**: GitHub Actions for automated deployment.

### Frontend
- **Hosting**: Netlify or Vercel.
- **CDN**: Cloudflare for improved performance.

---

## Best Practices
- Modular code structure for scalability.
- Secure API endpoints using JWT or OAuth2.
- Write unit tests for backend (PHPUnit) and frontend (Jest).
- Optimize audio file processing to ensure quick uploads.
- Implement responsive design for mobile and desktop.

---

## Overview Checklist and To-Do

### Phase 1: Core Functionality
- [ ] Set up secure user authentication and registration.
- [ ] Build the channel creation interface and playlist generator.
- [ ] Implement channel management features (On Air/Off Air, playlist controls).
- [ ] Integrate Liquidsoap or Icecast for audio streaming.
- [ ] Develop user interaction features (discover, follow, favorite channels).
- [ ] Introduce subscription model for additional channels.

### Phase 2: Advanced Features
- [ ] Add social features: comments, likes, and shares.
- [ ] Build community-driven playlists and trending sections.
- [ ] Develop analytics dashboards for premium users.

### Phase 3: Live Streaming and Event Tools
- [ ] Enable live audio streaming with real-time analytics.
- [ ] Create the event booking system with calendar integration.
- [ ] Add notifications and reminders for events.
- [ ] Develop the ticketing system with QR codes and payment integration.

### Deployment and Optimization
- [ ] Deploy backend on a reliable hosting platform.
- [ ] Set up CI/CD pipelines for smooth deployments.
- [ ] Optimize frontend for performance and mobile responsiveness.
- [ ] Implement caching and CDN for faster load times.
