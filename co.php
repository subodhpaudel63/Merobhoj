 <style>
        .quick-strip {
        background: linear-gradient(135deg, var(--brand), var(--brand-light));
        padding: 52px 0;
        text-align: center;
        position: relative;
        overflow: hidden;
      }
      .quick-strip::before {
        content: '';
        position: absolute; inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23fff' fill-opacity='0.06'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4z'/%3E%3C/g%3E%3C/svg%3E");
        pointer-events: none;
      }
      .quick-strip h2 { color: #fff; font-weight: 800; font-size: 1.75rem; margin-bottom: .5rem; position: relative; }
      .quick-strip p  { color: rgba(255,255,255,.8); font-size: .95rem; margin-bottom: 2rem; position: relative; }
      .strip-actions { display: flex; justify-content: center; gap: 16px; flex-wrap: wrap; position: relative; }
      .strip-btn {
        display: inline-flex; align-items: center; gap: 10px;
        padding: 14px 30px; border-radius: 50px;
        font-weight: 700; font-size: .9rem;
        text-decoration: none; letter-spacing: .04em;
        transition: all .25s cubic-bezier(.34,1.56,.64,1);
      }
      .strip-btn.primary {
        background: #fff; color: var(--brand);
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
      }
      .strip-btn.primary:hover { transform: translateY(-3px); box-shadow: 0 14px 36px rgba(0,0,0,0.2); }
      .strip-btn.outline {
        background: transparent;
        border: 2px solid rgba(255,255,255,0.6);
        color: #fff;
      }
      .strip-btn.outline:hover {
        background: rgba(255,255,255,0.15);
        border-color: #fff;
        transform: translateY(-3px);
      }
      </style>
 
      <section class="quick-strip" data-aos="fade-up">
        <div class="container">
          <h2>Prefer a Direct Call?</h2>
          <p>Our team is available 7 days a week. We're just a tap away.</p>
          <div class="strip-actions">
            <a href="tel:012978645312" class="strip-btn primary"><i class="fa fa-phone"></i> Call Now</a>
            <a href="mailto:MasukoJhol@gmail.com" class="strip-btn outline"><i class="fa fa-envelope"></i> Email Us</a>
          </div>
        </div>
      </section>