from fastapi import APIRouter, Request, Form, Depends
from fastapi.responses import RedirectResponse
from fastapi.templating import Jinja2Templates
from sqlalchemy.orm import Session
from app.db.session import SessionLocal
from app.db import models
from app.utils.security import hash_password, verify_password
import os

router = APIRouter()

# HTML şablonları dizini
templates = Jinja2Templates(directory=os.path.join(os.path.dirname(__file__), "../templates"))

# Veritabanı bağlantısı
def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

# -----------------------------------------------------------
# GET: Kayıt formunu göster
# -----------------------------------------------------------
@router.get("/register")
def show_register_form(request: Request):
    return templates.TemplateResponse("register.html", {
        "request": request,
        "error": None
    })

# -----------------------------------------------------------
# POST: Öğretmen kayıt işlemi
# -----------------------------------------------------------
@router.post("/register")
def register_post(
    request: Request,
    username: str = Form(...),
    email: str = Form(...),
    password: str = Form(...),
    role: str = Form(...),
    db: Session = Depends(get_db)
):
    if role != "teacher":
        return templates.TemplateResponse("register.html", {
            "request": request,
            "error": "Sadece öğretmenler kayıt olabilir."
        })

    existing_user = db.query(models.User).filter(models.User.email == email).first()
    if existing_user:
        return templates.TemplateResponse("register.html", {
            "request": request,
            "error": "Bu e-posta zaten kayıtlı."
        })

    hashed_pw = hash_password(password)
    new_user = models.User(
        email=email,
        username=username,
        hashed_password=hashed_pw,  # ✅ burada düzeltildi
        role=models.UserRole.teacher,
        is_approved=False
    )
    db.add(new_user)
    db.commit()

    return RedirectResponse("/auth/login", status_code=302)

# -----------------------------------------------------------
# GET: Giriş formunu göster
# -----------------------------------------------------------
@router.get("/login")
def show_login_form(request: Request):
    return templates.TemplateResponse("login.html", {
        "request": request,
        "error": None
    })

# -----------------------------------------------------------
# POST: Giriş işlemi
# -----------------------------------------------------------
@router.post("/login")
def login_post(
    request: Request,
    email: str = Form(...),
    password: str = Form(...),
    db: Session = Depends(get_db)
):
    db_user = db.query(models.User).filter(models.User.email == email).first()

    if not db_user or not verify_password(password, db_user.hashed_password):  # ✅ burada düzeltildi
        return templates.TemplateResponse("login.html", {
            "request": request,
            "error": "E-posta ya da şifre hatalı."
        })

    if not db_user.is_approved:
        return templates.TemplateResponse("login.html", {
            "request": request,
            "error": "Hesabınız henüz admin tarafından onaylanmadı."
        })

    # Rol bazlı yönlendirme
    if db_user.role == models.UserRole.admin:
        return RedirectResponse("/admin/users", status_code=302)
    elif db_user.role == models.UserRole.teacher:
        return RedirectResponse("/teacher/dashboard", status_code=302)
    elif db_user.role == models.UserRole.parent:
        return RedirectResponse("/parent/dashboard", status_code=302)
    elif db_user.role == models.UserRole.student:
        return RedirectResponse("/student/dashboard", status_code=302)

    # Bilinmeyen rol
    return templates.TemplateResponse("login.html", {
        "request": request,
        "error": "Geçersiz kullanıcı rolü!"
    })
