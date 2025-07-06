from fastapi import APIRouter, Request, Depends, Form
from fastapi.responses import RedirectResponse
from fastapi.templating import Jinja2Templates
from sqlalchemy.orm import Session
from app.db.session import SessionLocal
from app.db import models
import os

router = APIRouter()
templates = Jinja2Templates(directory=os.path.join(os.path.dirname(__file__), "../templates"))

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

# ---------------------------
# Admin paneli kullanıcı listesi
# ---------------------------
@router.get("/admin/users")
def admin_user_list(request: Request, db: Session = Depends(get_db)):
    users = db.query(models.User).all()
    return templates.TemplateResponse("admin_users.html", {"request": request, "users": users})


# ---------------------------
# Kullanıcıyı onayla
# ---------------------------
@router.post("/admin/approve")
def approve_user(user_id: int = Form(...), db: Session = Depends(get_db)):
    user = db.query(models.User).filter(models.User.id == user_id).first()
    if user:
        user.is_approved = True
        db.commit()
    return RedirectResponse("/admin/users", status_code=302)
