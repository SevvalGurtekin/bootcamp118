from fastapi import APIRouter, Request, Depends
from fastapi.templating import Jinja2Templates
from fastapi.responses import RedirectResponse
from sqlalchemy.orm import Session
from app.db.session import SessionLocal
from app.db import models
import os

router = APIRouter()
templates = Jinja2Templates(directory="app/templates")

def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

# ------------------------------------------
# Admin Dashboard - Onay Bekleyenler
# ------------------------------------------
@router.get("/admin/dashboard")
def admin_dashboard(request: Request, db: Session = Depends(get_db)):
    pending_users = db.query(models.User).filter(models.User.is_active == False, models.User.role == "teacher").all()
    return templates.TemplateResponse("admin_dashboard.html", {
        "request": request,
        "pending_users": pending_users
    })

# ------------------------------------------
# Kullanıcıyı Onayla
# ------------------------------------------
@router.get("/admin/approve/{user_id}")
def approve_user(user_id: int, db: Session = Depends(get_db)):
    user = db.query(models.User).filter(models.User.id == user_id).first()
    if user:
        user.is_active = True
        db.commit()
    return RedirectResponse("/admin/dashboard", status_code=302)
